<?php
namespace Jovencio\DataTable;

use Carbon\Carbon;
use Illuminate\Http\Request;

class DataTableQueryFactory {
    protected $request;
    private $formatDateLocale = 'm/d/Y';
    private $timezoneLocale = '+00:00';
    private $timezoneApp = '+00:00';
    
    public function __construct(Request $request)
    {
        $this->request = $request;
        if ($request->has('format_date_locale')) {
            $this->formatDateLocale = $request->get('format_date_locale');
        }

        $this->timezoneApp = $this->getTimezoneOffset(config('app.timezone'));
        $this->timezoneLocale = $this->getTimezoneOffset(config('app.timezone'));
        if ($request->has('timezone_locale')) {
            $this->timezoneLocale = $this->getTimezoneOffset($request->get('timezone_locale'));
        }
    }

    public function build($model, $config = [
        'query'     => [],
        'with'      => [],
        'select'    => [],
        'map'       => null
    ]) {
        $params         = $this->request->all();

        $draw           = $this->request->get('draw') ?? "0";
        $start          = $this->request->get('start') ?? 0;
        $length         = $this->request->get('length') ?? 10;

        $customQuery    = isset($config['query']) && \is_array($config['query']) && \count($config['query']) ? $config['query'] : [];
        $withQuery      = isset($config['with']) && \is_array($config['with']) && \count($config['with']) ? $config['with'] : null;
        $map            = isset($config['map']) && \is_callable($config['map']) ? $config['map'] : null;
        $select         = isset($config['select']) && \is_array($config['select']) && \count($config['select']) ? $config['select'] : null;

        $userQuery = $this->constructorQueryDataTable($model::query(), $params, $customQuery);

        $total = $userQuery->count();
        $userQuery = self::constructorOrderByDataTable($userQuery, $params);
        
        $data = $userQuery->skip($start)->limit($length);
        
        if ($withQuery) {
            $data->with($withQuery);
        }

        if ($select) {
            $data->select($select);
        }

        $data = $data->get();

        if ($map) {
            $data = $data->map($map)->values();
        }

        if ($data->first() && !property_exists((object)$data->first(), 'actions')) {
            $data->transform(function($row) {
                if (\is_object($row))
                    $row->actions = '';
                if (\is_array($row))
                    $row['actions'] = '';
                
                return $row;
            });
        }
        
        return array(
            'draw' => $draw,
            'recordsTotal' => $total,
            'recordsFiltered' => $total,
            'data' => $data,
        );
    }

    private function constructorQueryDataTable($model, $post, $matchColumns) {

        $query = " ";
        $queryParam = [];
        if (isset($post["searchBuilder"]["criteria"]) && isset($post["searchBuilder"]["criteria"]) && count($post["searchBuilder"]["criteria"])) {

            $oneThree = $post["searchBuilder"]["criteria"];
            $logic1 = $post["searchBuilder"]["logic"];
            $lastKey = array_key_last($oneThree);

            foreach ($oneThree as $key => $row) {
                
                if (isset($row['logic'])) {

                    $logic2 = $row['logic'];
                    $query2 = '';

                    $lastKey2 = array_key_last($row['criteria']);

                    foreach ($row['criteria'] as $key2 => $row2) {
                
                        if (isset($row2['logic'])) {


                            $logic3 = $row2['logic'];
                            $query3 = '';

                            $lastKey3 = array_key_last($row2['criteria']);

                            foreach ($row2['criteria'] as $key3 => $row3) {
                        
                                if (isset($row3['logic'])) {
                                    // limit 3
                                } else {
                
                                    if (isset($matchColumns[$row3["origData"] ?? null])) {
                                        list($auxQuery, $params) = $matchColumns[$row3["origData"]]($row3);
                                    } else {
                                        list($auxQuery, $params) = $this->_matchCondiction($row3["condition"] ?? null, $row3["origData"] ?? null, $row3["value"] ?? [], $row["type"] ?? "string");
                                    }

                                    if (!empty($params) && is_array($params))
                                        array_push($queryParam, ...$params);
                                    

                                    if ($lastKey3 != $key3 && $auxQuery) {
                                        $query3 .= " ({$auxQuery}) {$logic3} ";
                                    } else if ($auxQuery) {
                                        $query3 .= " ({$auxQuery}) ";
                                    }
                                }
                            }

                            // LOGICA DO INDICE 2, COLOCA TODA A QUERY DA ARVORE NO INDICE 3 NA QUERY DO INDICE 2
                            if ($lastKey2 != $key2 && $auxQuery) {
                                $query2 .= " ({$auxQuery}) {$logic2} ";
                            } else if ($auxQuery) {
                                $query2 .= " ({$auxQuery}) ";
                            }

                        } else {
        
                            if (isset($matchColumns[$row2["origData"] ?? null])) {
                                list($auxQuery, $params) = $matchColumns[$row2["origData"]]($row2);
                            } else {
                                list($auxQuery, $params) = $this->_matchCondiction($row2["condition"] ?? null, $row2["origData"] ?? null, $row2["value"], $row["type"] ?? "string");
                            }

                            if (!empty($params) && is_array($params))
                                array_push($queryParam, ...$params);
                            
                            // LOGICA DO INDICE 2
                            if ($lastKey2 != $key2 && $auxQuery) {
                                $query2 .= " ({$auxQuery}) {$logic2} ";
                            } else if ($auxQuery) {
                                $query2 .= " ({$auxQuery}) ";
                            }
                        }

                    }


                    // LOGICA DO INDICE 1, COLOCA TODA A QUERY DA ARVORE NO INDICE 2 NA QUERY DO INDICE 1
                    if ($lastKey != $key && $auxQuery) {
                        $query .= " ({$query2}) {$logic1} ";
                    } else if ($auxQuery) {
                        $query .= " ({$query2}) ";
                    }

                } else {

                    if (isset($matchColumns[$row["origData"] ?? null])) {
                        list($auxQuery, $params) = $matchColumns[$row["origData"]]($row);
                    } else {
                        list($auxQuery, $params) = $this->_matchCondiction($row["condition"] ?? null, $row["origData"] ?? null, $row["value"] ?? [], $row["type"] ?? "string");
                    }

                    if (!empty($params) && is_array($params))
                        array_push($queryParam, ...$params);

                    if ($lastKey != $key && $auxQuery) {
                        $query .= " ({$auxQuery}) {$logic1} ";
                    } else if ($auxQuery) {
                        $query .= " ({$auxQuery}) ";
                    }
                }
            }
        }

        $searchQuery = '';
        $searchParam = [];

        if (!empty($post["search"]) && !empty($post["search"]["value"])) {
            $searchs = array_values((array_filter($post["columns"], function($row) {
                return filter_var($row["searchable"], FILTER_VALIDATE_BOOLEAN);
            })));

            $lastKey = array_key_last($searchs);
            $searchQuery .= ' (';
            foreach($searchs as $key => $search) {
                if (isset($matchColumns[$search["data"] ?? null])) {
                    list($auxQuery, $params) = $matchColumns[$search["data"]]([
                        'condition' => 'contains',
                        'value' => $post["search"]["value"],
                    ]);
                } else {
                    list($auxQuery, $params) = $this->_matchCondiction('contains', $search["data"] ?? null, [$post["search"]["value"]], $row["type"] ?? "string");
                }

                array_push($searchParam, ...$params);
                if ($lastKey != $key && $auxQuery) {
                    $searchQuery .= " ({$auxQuery}) OR ";
                } else if ($auxQuery) {
                    $searchQuery .= " ({$auxQuery}) ";
                }
            }
            $searchQuery .= ') ';
        }

        if ($query != ' ')
            $model->whereRaw($query, $queryParam);

        if (!empty($searchQuery))
            $model->whereRaw($searchQuery, $searchParam);

        return $model;
    }

    private function getTimezoneOffset($timezone = "UTC") {
        $carbon = Carbon::now($timezone);
        $offset = $carbon->utcOffset(); 
        $offsetInHours = $offset / 60;

        if ($offsetInHours >= 0) {
            $offsetInHours = "+".$offsetInHours;
        }
        return $offsetInHours.":00";
    }

    private static function constructorOrderByDataTable($model, $post) {
        if (!empty($post["order"]) && count($post["order"])) {
            $orders = $post["columns"];

            $orderByRaw = ' ';
            $lastKey = array_key_last($post["order"]);

            foreach($post["order"] as $key => $column) {
                if (empty($orders[$column['column']])) {
                    continue;
                }

                $col = $orders[$column['column']]['data'];
                $dir = $column['dir'];
                
                if ($lastKey != $key) {
                    $orderByRaw .= " {$col} {$dir}, ";
                } else {
                    $orderByRaw .= " {$col} {$dir} ";
                }
            }

            if (!empty($orderByRaw)) {
                $model->orderByRaw($orderByRaw);
            }
        }

        return $model;
    }

    private function formatValue($value, $type) {
        switch ($type) {
            case "moment": {
                $formatDateLocale = match ($this->formatDateLocale) {
                    "DD/MM/YYYY" => 'd/m/Y',
                    default => 'm/d/Y'
                };
                return Carbon::createFromFormat($formatDateLocale, $value)->format('Y-m-d');
            }
            default: {
                return $value;
            }
        }
    }
    
    private function _matchCondiction($condition, $column, $param, $type = 'string') :array {
        if (empty($column) || (!in_array($condition, ['null', '!null']) && (empty($param) || is_null($param[0]) || $param[0] == ' ' || $param[0] == '' ))) return [null, null];
        if (in_array($condition, ['between', '!between']) && (empty($param[0]) || empty($param[1]))) return [null, null];

        switch ($type) {
            case 'date':
            case 'moment':
                
                $query = match ($condition) {
                    'between' => " CONVERT_TZ({$column}, '{$this->timezoneApp}', '{$this->timezoneLocale}')  BETWEEN ? AND ? ",
                    '!between' => " CONVERT_TZ({$column}, '{$this->timezoneApp}', '{$this->timezoneLocale}') NOT  BETWEEN ? AND ? ",
                    'null' => " {$column} IS NULL ",
                    '!null' => " {$column} IS NOT NULL ",
                    default => " DATE(CONVERT_TZ({$column}, '{$this->timezoneApp}', '{$this->timezoneLocale}')) {$condition} ? "
                };
                break;
            default:
                $query = match ($condition) {
                    'between' => " {$column} BETWEEN ? AND ? ",
                    '!between' => " {$column} NOT  BETWEEN ? AND ? ",
                    'null' => " {$column} IS NULL ",
                    '!null' => " {$column} IS NOT NULL ",
                    'starts' => " {$column} LIKE ? ",
                    '!starts' => " {$column} NOT LIKE ? ",
                    'contains' => " {$column} LIKE ? ",
                    '!contains' => " {$column} NOT LIKE ? ",
                    'ends' => " {$column} LIKE ? ",
                    '!ends' => " {$column} NOT LIKE ? ",
                    default => " {$column} {$condition} ? "
                };
                break;
        }

        $params = [];
        // Adiciona parâmetros ao array
        switch ($condition) {
            case 'between':
            case '!between':
                $params[] = $this->formatValue($param[0], $type) . ((in_array($type, ["date", "moment"])) ? " 00:00:00" : '');
                $params[] = $this->formatValue($param[1], $type) . ((in_array($type, ["date", "moment"])) ? " 23:59:59" : '');
                break;
            case 'starts':
            case '!starts':
                $params[] = "{$this->formatValue($param[0], $type)}%";
                break;
            case 'contains':
            case '!contains':
                $params[] = "%{$this->formatValue($param[0], $type)}%";
                break;
            case 'ends':
            case '!ends':
                $params[] = "%{$this->formatValue($param[0], $type)}";
                break;
            default:
                $params[] = $this->formatValue($param[0], $type);
                break;
        }

        return [$query, $params];
    }

    public static function matchCondiction($condition, $column, $param, $type = 'string') :array {
        return (new self(request()))->_matchCondiction($condition, $column, $param, $type);
    }
}
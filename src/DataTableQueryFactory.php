<?php
namespace Jovencio\DataTable;

use Carbon\Carbon;
use DateTime;
use DateTimeZone;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB as DBLaravel;
use Illuminate\Database\Capsule\Manager as DBManager;

class DataTableQueryFactory {
    protected $request;
    private $formatDateLocale = 'm/d/Y';
    private $timezoneLocale = '+00:00';
    private $timezoneLocaleName = 'UTC';
    private $timezoneApp = '+00:00';
    private $timezoneAppName = 'UTC';
    private $tableName = '';
    private $timezone = [];
    
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
            $this->timezoneLocaleName = $request->get('timezone_locale');
        }
    }

    public function build($model, $config = [
        'query'     => [],
        'with'      => [],
        'select'    => [],
        'map'       => null,
        'timezone'  => [],
        "where"     => null
    ]) {
        $params         = $this->request->all();
        $this->tableName = (new $model)->getTable();

        $draw           = $this->request->get('draw') ?? "0";
        $start          = $this->request->get('start') ?? 0;
        $length         = $this->request->get('length') ?? 10;

        $customQuery    = isset($config['query']) && \is_array($config['query']) && \count($config['query']) ? $config['query'] : [];
        $withQuery      = isset($config['with']) && \is_array($config['with']) && \count($config['with']) ? $config['with'] : null;
        $map            = isset($config['map']) && \is_callable($config['map']) ? $config['map'] : null;
        $select         = isset($config['select']) && \is_array($config['select']) && \count($config['select']) ? $config['select'] : null;
        $this->timezone = isset($config['timezone']) && \is_array($config['timezone']) && \count($config['timezone']) ? $config['timezone'] : [];

        $userQuery = $this->constructorQueryDataTable($model::query(), $params, $customQuery);

        if (isset($config['where']) && is_callable($config['where'])) {
            $userQuery->where($config['where']);
        }

        $total = $userQuery->count();
        $userQuery = $this->constructorOrderByDataTable($userQuery, $params);
        
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

            $oneTree = $post["searchBuilder"]["criteria"];
            $oneTree = array_values(array_filter($oneTree, function($row) {
                return count(array_filter($row['value'] ?? [], function($value) {
                    return !is_null($value) && trim($value ?? "") != '';
                })) || array_key_exists('logic', $row);
            }));

            $logic1 = $post["searchBuilder"]["logic"];
            $lastKey = array_key_last($oneTree);


            foreach ($oneTree as $key => $row) {
                
                if (isset($row['logic'])) {

                    $logic2 = $row['logic'];
                    $query2 = '';

                    $secondTree = $row['criteria'];
                    $secondTree = array_values(array_filter($secondTree, function($row) {
                        return count(array_filter($row['value'] ?? [], function($value) {
                            return !is_null($value) && trim($value ?? "") != '';
                        })) || array_key_exists('logic', $row);
                    }));

                    $lastKey2 = array_key_last($secondTree);

                    foreach ($secondTree as $key2 => $row2) {
                
                        if (isset($row2['logic'])) {
                            // Inicio da arvore 3
                            $logic3 = $row2['logic'];
                            $query3 = '';

                            $threeTree = $row2['criteria'];
                            $threeTree = array_values(array_filter($threeTree, function($row) {
                                return count(array_filter($row['value'] ?? [], function($value) {
                                    return !is_null($value) && trim($value ?? "") != '';
                                })) || array_key_exists('logic', $row);
                            }));

                            $lastKey3 = array_key_last($threeTree);

                            foreach ($threeTree as $key3 => $row3) {
                        
                                if (isset($row3['logic'])) {
                                    // limit 3
                                } else {
                
                                    if (isset($matchColumns[$row3["origData"] ?? null])) {
                                        list($auxQuery3, $params) = $matchColumns[$row3["origData"]]($row3, $this->tableName, function($condition, $column, $param, $type = "string") {
                                            return $this->_matchConditional($condition, $column, $param, $type);
                                        });
                                    } else {
                                        list($auxQuery3, $params) = $this->_matchConditional($row3["condition"] ?? null, $row3["origData"] ?? null, $row3["value"] ?? [], $row3["type"] ?? "string");
                                    }

                                    if (!empty($params) && is_array($params))
                                        array_push($queryParam, ...$params);

                                    if ($lastKey3 != $key3 && !empty($auxQuery3)) {
                                        $query3 .= " ({$auxQuery3}) {$logic3} ";
                                    } else if (!empty($auxQuery3)) {
                                        $query3 .= " ({$auxQuery3}) ";
                                    }
                                }
                                // Fim da arvore 3.
                            }

                            // LOGICA DO INDICE 2, COLOCA TODA A QUERY DA ARVORE DO INDICE 3 NA QUERY DO INDICE 2
                            if ($lastKey2 != $key2 && !empty($query3)) {
                                $query2 .= " ({$query3}) {$logic2} ";
                            } else if (!empty($query3)) {
                                $query2 .= " ({$query3}) ";
                            }

                        } else {
        
                            if (isset($matchColumns[$row2["origData"] ?? null])) {
                                list($auxQuery2, $params) = $matchColumns[$row2["origData"]]($row2, $this->tableName, function($condition, $column, $param, $type = "string") {
                                    return $this->_matchConditional($condition, $column, $param, $type);
                                });
                            } else {
                                list($auxQuery2, $params) = $this->_matchConditional($row2["condition"] ?? null, $row2["origData"] ?? null, $row2["value"], $row2["type"] ?? "string");
                            }

                            if (!empty($params) && is_array($params))
                                array_push($queryParam, ...$params);
                            
                            // LOGICA DO INDICE 2
                            if ($lastKey2 != $key2 && !empty($auxQuery2)) {
                                $query2 .= " ({$auxQuery2}) {$logic2} ";
                            } else if (!empty($auxQuery2)) {
                                $query2 .= " ({$auxQuery2}) ";
                            }
                        }
                    }

                    // LOGICA DO INDICE 1, COLOCA TODA A QUERY DA ARVORE DO INDICE 2 NA QUERY DO INDICE 1
                    if ($lastKey != $key && !empty($query2)) {
                        $query .= " ({$query2}) {$logic1} ";
                    } else if (!empty($query2)) {
                        $query .= " ({$query2}) ";
                    }

                } else {

                    if (isset($matchColumns[$row["origData"] ?? null])) {
                        list($auxQuery1, $params) = $matchColumns[$row["origData"]]($row, $this->tableName, function($condition, $column, $param, $type = "string") {
                            return $this->_matchConditional($condition, $column, $param, $type);
                        });
                    } else {
                        list($auxQuery1, $params) = $this->_matchConditional($row["condition"] ?? null, $row["origData"] ?? null, $row["value"] ?? [], $row["type"] ?? "string");
                    }

                    if (!empty($params) && is_array($params))
                        array_push($queryParam, ...$params);

                    if ($lastKey != $key && !empty($auxQuery1)) {
                        $query .= " ({$auxQuery1}) {$logic1} ";
                    } else if (!empty($auxQuery1)) {
                        $query .= " ({$auxQuery1}) ";
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
                        'value' => [$post["search"]["value"]],
                        'type' => 'string'
                    ], $this->tableName, function($condition, $column, $param, $type = "string") {
                        return $this->_matchConditional($condition, $column, $param, $type);
                    });
                } else {
                    list($auxQuery, $params) = $this->_matchConditional('contains', $search["data"] ?? null, [$post["search"]["value"]], $row["type"] ?? "string");
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

    private function constructorOrderByDataTable($model, $post) {
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

    private function hasTimestamp($column) {
        switch (strtolower(config('database.default'))) {
            case 'sqlite':
                $columnType = null;
                try {
                    $columnTypeLite = DBLaravel::select("PRAGMA table_info({$this->tableName})");
                } catch (\Exception $th) {
                    $columnTypeLite = DBManager::select("PRAGMA table_info({$this->tableName})");
                }

                foreach ($columnTypeLite as $columnLite) {
                    if ($columnLite->name === $column) {
                        $columnType = $columnLite->type;
                        break;
                    }
                }
                break;
            case 'pgsql':
                try {
                    $columnType = DBLaravel::table('information_schema.columns')
                    ->where('table_name', $this->tableName)
                    ->where('column_name', $column)
                    ->where('table_schema', 'public')
                    ->first();
                } catch (\Exception $th) {
                    $columnType = DBManager::table('information_schema.columns')
                    ->where('table_name', $this->tableName)
                    ->where('column_name', $column)
                    ->where('table_schema', 'public')
                    ->first();
                }
                
                $columnType = $columnType->data_type ? $columnType->data_type : null;
                break;
            case 'mysql':
            case 'mariadb':
                try {
                    $columnType = DBLaravel::table('information_schema.columns')
                    ->where('table_name', $this->tableName)
                    ->where('column_name', $column)
                    ->first();
                } catch (\Exception $th) {
                    $columnType = DBManager::table('information_schema.columns')
                    ->where('table_name', $this->tableName)
                    ->where('column_name', $column)
                    ->first();
                }
                $columnType = $columnType->COLUMN_TYPE ? $columnType->COLUMN_TYPE : null;
                break;
            case 'sqlsrv':
                try {
                    $columnType = DBLaravel::table('INFORMATION_SCHEMA.COLUMNS')
                    ->where('TABLE_NAME', $this->tableName)
                    ->where('COLUMN_NAME', $column)
                    ->first();
                } catch (\Exception $th) {
                    $columnType = DBManager::table('INFORMATION_SCHEMA.COLUMNS')
                    ->where('TABLE_NAME', $this->tableName)
                    ->where('COLUMN_NAME', $column)
                    ->first();
                }
                $columnType = $columnType->DATA_TYPE ? $columnType->DATA_TYPE : null;
                break;
        }

        return in_array(strtolower($columnType), ['timestamp', 'timestamptz', 'datetime']);
    }

    private function formatValue($column, $value, $type) {

        $defaultReturn = function($column, $formatDateLocale, $value) {
            $hasTimestamp = $this->hasTimestamp($column);
            $dateFormat = !empty($this->timezone[$column]["date_format"]["php"]) ? $this->timezone[$column]["date_format"]["php"] : 'Y-m-d H:i';
            if ($hasTimestamp) {
                $date = DateTime::createFromFormat($formatDateLocale, $value, new DateTimeZone($this->timezoneLocaleName));
                $date->setTimezone(new DateTimeZone($this->timezoneAppName));
                $formattedDate = $date->format($dateFormat);
                return $formattedDate;
            }
            
            $date = DateTime::createFromFormat($formatDateLocale, $value);
            $formattedDate = $date->format($dateFormat);
            return $formattedDate;
        };

        switch (strtolower($type)) {
            case "date":
            case "moment":

                $formatDateLocale = match ($this->formatDateLocale) {
                    "DD/MM/YYYY HH:mm" => 'd/m/Y H:i',
                    default => 'm/d/Y h:i A'
                };

                if (!array_key_exists($column, $this->timezone)) {
                    return $defaultReturn($column, $formatDateLocale, $value);
                }

                if (!array_key_exists('enable', $this->timezone[$column]) || empty($this->timezone[$column]["utc"])) {
                    return $defaultReturn($column, $formatDateLocale, $value);
                }

                $dateFormat = !empty($this->timezone[$column]["date_format"]["php"]) ? $this->timezone[$column]["date_format"]["php"] : 'Y-m-d H:i';
                if ($this->timezone[$column]["enable"]) {
                    $date = DateTime::createFromFormat($formatDateLocale, $value, new DateTimeZone($this->timezoneLocaleName));
                    $date->setTimezone(new DateTimeZone($this->timezone[$column]["utc"]));
                    $formattedDate = $date->format($dateFormat);
                    return $formattedDate;
                }

                $date = DateTime::createFromFormat($formatDateLocale, $value);
                $formattedDate = $date->format($dateFormat);
                return $formattedDate;
            case "num":
            case "num-fmt":
                if (is_numeric($value)) {
                    return floatval($value);
                }
                return $value;
            default:
                return $value;
        }
    }

    private function makeQuery($type, $condition, $column) {
        switch (strtolower($type)) {
            case 'date':
            case 'moment':
                $query = match ($condition) {
                    'between' =>  " {$column} BETWEEN ? AND ? ",
                    '!between' => " {$column} NOT BETWEEN ? AND ? ",
                    'null' => " {$column} IS NULL ",
                    '!null' => " {$column} IS NOT NULL ",
                    default => null
                };
                
                if (is_null($query)) {
                    switch (strtolower(config('database.default'))) {
                        case 'sqlite':
                            $dateFormat = !empty($this->timezone[$column]["date_format"]["sql"]) ? $this->timezone[$column]["date_format"]["sql"] : '%Y-%m-%d %H:%M';
                            $query = " strftime('{$dateFormat}', {$column}) {$condition} ? ";
                            break;
                        case 'pgsql':
                            $dateFormat = !empty($this->timezone[$column]["date_format"]["sql"]) ? $this->timezone[$column]["date_format"]["sql"] : 'YYYY-MM-DD HH24:MI';
                            $query = " to_char({$column}, '{$dateFormat}') {$condition} ? ";
                            break;
                        case 'mysql':
                        case 'mariadb':
                            $dateFormat = !empty($this->timezone[$column]["date_format"]["sql"]) ? $this->timezone[$column]["date_format"]["sql"] : '%Y-%m-%d %H:%i';
                            $query = " DATE_FORMAT({$column}, '{$dateFormat}') {$condition} ? ";
                            break;
                        case 'sqlsrv':
                            $dateFormat = !empty($this->timezone[$column]["date_format"]["sql"]) ? $this->timezone[$column]["date_format"]["sql"] : 'yyyy-MM-dd HH:mm';
                            $query = " FORMAT({$column}, '{$dateFormat}') {$condition} ? ";
                            break;
                    }
                }
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
        return $query;
    }

    private function makeParams($type, $condition, $column, $param) {
        $params = [];
        $typeDate = in_array(strtolower($type), ['date', 'moment']);
        
        if (!is_array($param)) {
            $param = [$param];
        }

        switch (strtolower($condition)) {
            case 'between':
            case '!between':
                $params[] = $this->formatValue($column, $param[0], $type) . (($typeDate) ? ":00" : '');
                $params[] = $this->formatValue($column, $param[1], $type) . (($typeDate) ? ":59" : '');
                break;
            case 'starts':
            case '!starts':
                $params[] = "{$this->formatValue($column, $param[0], $type)}%";
                break;
            case 'contains':
            case '!contains':
                $params[] = "%{$this->formatValue($column, $param[0], $type)}%";
                break;
            case 'ends':
            case '!ends':
                $params[] = "%{$this->formatValue($column, $param[0], $type)}";
                break;
            default:
                $params[] = $this->formatValue($column, $param[0], $type);
                break;
        }
        return $params;
    }

    private function _matchConditional($condition, $column, $param, $type = 'string') :array {
        if (empty($column) || (!in_array(strtolower($condition), ['null', '!null']) && (empty($param) || is_null($param[0]) || $param[0] == ' ' || $param[0] == '' ))) return [null, null];
        if (in_array(strtolower($condition), ['between', '!between']) && (empty($param[0]) || empty($param[1]))) return [null, null];

        $query = $this->makeQuery($type, $condition, $column);
        $params = $this->makeParams($type, $condition, $column, $param);
        
        return [$query, $params];
    }

    /**
     * The function is deprecated. The clause is now provided in the third parameter of the column query customization closure.
     *
     * @deprecated The function is deprecated. The clause is now provided in the third parameter of the column query customization closure.
     */
    public static function matchCondiction($condition, $column, $param, $type = 'string') :array {
        return (new self(request()))->_matchConditional($condition, $column, $param, $type);
    }
}
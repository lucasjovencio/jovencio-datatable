<?php
namespace Jovencio\DataTable;

use Illuminate\Http\Request;

class DataTableQueryFactory {
    protected $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
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

        $customQuery    = isset($config['query']) && \count($config['query']) ? $config['query'] : [];
        $withQuery      = isset($config['with']) && \count($config['with']) ? $config['with'] : null;
        $map            = isset($config['map']) && \is_callable($config['map']) ? $config['map'] : null;
        $select         = isset($config['select']) && \count($config['select']) ? $config['select'] : null;

        $userQuery = self::constructorQueryDataTable($model::query(), $params, $customQuery);

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
        
        return array(
            'draw' => $draw,
            'recordsTotal' => $total,
            'recordsFiltered' => $total,
            'data' => $data,
        );
    }

    private static function constructorQueryDataTable($model, $post, $matchColumns) {

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
                                        list($auxQuery, $params) = self::matchCondiction($row3["condition"] ?? null, $row3["origData"] ?? null, $row3["value"] ?? []);
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
                                list($auxQuery, $params) = self::matchCondiction($row2["condition"] ?? null, $row2["origData"] ?? null, $row2["value"]);
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
                        list($auxQuery, $params) = self::matchCondiction($row["condition"] ?? null, $row["origData"] ?? null, $row["value"] ?? []);
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
        if (!empty($post["search"]) && !empty($post["search"]["value"])) {
            $searchs = array_values((array_filter($post["columns"], function($row) {
                return filter_var($row["searchable"], FILTER_VALIDATE_BOOLEAN);
            })));

            $lastKey = array_key_last($searchs);
            $searchQuery .= ' (';
            foreach($searchs as $key => $search) {
                list($auxQuery, $params) = self::matchCondiction('contains', $search["data"] ?? null, [$post["search"]["value"]]);
                array_push($queryParam, ...$params);
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
            $model->whereRaw($searchQuery, $queryParam);

        return $model;
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

    public static function matchCondiction($condition, $column, $param) :array {
        if (empty($column) || (!in_array($condition, ['null', '!null']) && (empty($param) || is_null($param[0]) || $param[0] == ' ' || $param[0] == '' ))) return [null, null];
        if (in_array($condition, ['between', '!between']) && (empty($param[0]) || empty($param[1]))) return [null, null];

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

        $params = [];
        // Adiciona parâmetros ao array
        switch ($condition) {
            case 'between':
            case '!between':
                $params[] = "{$param[0]} 00:00:00";
                $params[] = "{$param[1]} 23:59:59";
                break;
            case 'starts':
            case '!starts':
                $params[] = "{$param[0]}%";
                break;
            case 'contains':
            case '!contains':
                $params[] = "%{$param[0]}%";
                break;
            case 'ends':
            case '!ends':
                $params[] = "%{$param[0]}";
                break;
            default:
                $params[] = $param[0];
                break;
        }

        return [$query, $params];
    }
}
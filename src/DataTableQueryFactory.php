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
        $this->timezoneAppName  = config('app.timezone');
        $this->timezoneApp      = $this->getTimezoneOffset(config('app.timezone'));
        $this->timezoneLocale   = $this->getTimezoneOffset(config('app.timezone'));

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

        // Função recursiva para processar critérios aninhados
        $processCriteria = function ($criteria, $logic, &$queryParam) use (&$processCriteria, $matchColumns) {
            $parts = [];
            foreach ($criteria as $row) {
                if (isset($row['logic']) && isset($row['criteria'])) {
                    // Recursão para subníveis
                    $sub = $processCriteria($row['criteria'], $row['logic'], $queryParam);
                    if ($sub !== '') {
                        $parts[] = "({$sub})";
                    }
                } else {
                    if (isset($matchColumns[$row["origData"] ?? null])) {
                        list($auxQuery, $params) = $matchColumns[$row["origData"]]($row, $this->tableName, function($condition, $column, $param, $type = "string", $columnDT = null) {
                            return $this->_matchConditional($condition, $column, $param, $type, $columnDT);
                        });
                    } else {
                        list($auxQuery, $params) = $this->_matchConditional((isset($row['origCond']) && !empty(isset($row['origCond']))) ? $row['origCond'] : $row["condition"] ?? null, $row["origData"] ?? null, $row["value"] ?? [], $row["type"] ?? "string", $row["condition"] ?? null);
                    }
                    if (!empty($params) && is_array($params)) {
                        array_push($queryParam, ...$params);
                    }
                    if (!empty($auxQuery)) {
                        $parts[] = "({$auxQuery})";
                    }
                }
            }
            return implode(" {$logic} ", $parts);
        };

        if (
            isset($post["searchBuilder"]["criteria"]) &&
            is_array($post["searchBuilder"]["criteria"]) &&
            count($post["searchBuilder"]["criteria"])
        ) {
            $logic = $post["searchBuilder"]["logic"] ?? 'AND';
            $criteria = $post["searchBuilder"]["criteria"];
            $queryBuilt = $processCriteria($criteria, $logic, $queryParam);
            if (!empty($queryBuilt)) {
                $query .= $queryBuilt;
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
                    ], $this->tableName, function($condition, $column, $param, $type = "string", $columnDT = null) {
                        return $this->_matchConditional($condition, $column, $param, $type, $columnDT);
                    });
                } else {
                    list($auxQuery, $params) = $this->_matchConditional('contains', $search["data"] ?? null, [$post["search"]["value"]], $row["type"] ?? "string", null);
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

            foreach($post["order"] as $key => $column) {
                if (empty($orders[$column['column']])) {
                    continue;
                }

                $col = $orders[$column['column']]['data'];
                $dir = strtolower($column['dir']);

                if (!preg_match('/^[a-zA-Z0-9_\.]+$/', $col)) {
                    continue;
                }

                // Validação da direção
                if (!in_array($dir, ['asc', 'desc'])) {
                    $dir = 'asc';
                }

                $model->orderBy($col, $dir);
            }
        }

        return $model;
    }

    private function hasTimestamp($column) {
        list($table, $columnAux) = explode('.', $column);
        if (empty($columnAux)) {
            $table = $this->tableName;
        } else {
            $column = $columnAux;
        }
        switch (strtolower(config('database.default'))) {
            case 'sqlite':
                $columnType = null;
                try {
                    $columnTypeLite = DBLaravel::select("PRAGMA table_info({$table})");
                } catch (\Exception $th) {
                    $columnTypeLite = DBManager::select("PRAGMA table_info({$table})");
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
                    ->where('table_name', $table)
                    ->where('column_name', $column)
                    ->where('table_schema', 'public')
                    ->first();
                } catch (\Exception $th) {
                    $columnType = DBManager::table('information_schema.columns')
                    ->where('table_name', $table)
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
                    ->where('table_name', $table)
                    ->where('column_name', $column)
                    ->first();
                } catch (\Exception $th) {
                    $columnType = DBManager::table('information_schema.columns')
                    ->where('table_name', $table)
                    ->where('column_name', $column)
                    ->first();
                }
                $columnType = $columnType->COLUMN_TYPE ? $columnType->COLUMN_TYPE : null;
                break;
            case 'sqlsrv':
                try {
                    $columnType = DBLaravel::table('INFORMATION_SCHEMA.COLUMNS')
                    ->where('TABLE_NAME', $table)
                    ->where('COLUMN_NAME', $column)
                    ->first();
                } catch (\Exception $th) {
                    $columnType = DBManager::table('INFORMATION_SCHEMA.COLUMNS')
                    ->where('TABLE_NAME', $table)
                    ->where('COLUMN_NAME', $column)
                    ->first();
                }
                $columnType = $columnType->DATA_TYPE ? $columnType->DATA_TYPE : null;
                break;
        }

        return in_array(strtolower($columnType), ['timestamp', 'timestamptz', 'datetime']);
    }

    private function formatValue($column, $columnDT, $value, $type) {

        $valueAux = explode(' ', $value);
        $hasTime = false;
        if (1 !== count($valueAux) && in_array(strtolower($type), ['date', 'moment'])) {
            $hasTime = true;
        }

        $timezoneMatch = null;
        if (
            isset($this->timezone[$columnDT]) &&
            isset($this->timezone[$columnDT]['format'], $this->timezone[$columnDT]['format']['php'])
        ) {
            $timezoneMatch = $this->timezone[$columnDT];
        } elseif (
            isset($this->timezone[$column]) &&
            isset($this->timezone[$column]['format'], $this->timezone[$column]['format']['php'])
        ) {
            $timezoneMatch = $this->timezone[$column];
        }

        $defaultReturn = function($column, $formatDateLocale, $value) use($hasTime, $timezoneMatch) {
            try {
                $hasTimestamp   = $this->hasTimestamp($column);
            } catch (\Exception $e) {
                $hasTimestamp = false;
            }
            
            $dateFormat     = !empty($timezoneMatch) && !empty($timezoneMatch["format"]["php"]) && $timezoneMatch["enable"] ? $timezoneMatch["format"]["php"] : 'Y-m-d H:i';
            $from           = !empty($timezoneMatch) && !empty($timezoneMatch["format"]["front"]) && $timezoneMatch["enable"] ? $timezoneMatch["format"]["front"] : $formatDateLocale;
            $appTimezone    = !empty($timezoneMatch) && !empty($timezoneMatch["timezone"]["app"]) && $timezoneMatch["enable"] ? $timezoneMatch["timezone"]["app"] : $this->timezoneAppName;
            $clientTimezone = !empty($timezoneMatch) && !empty($timezoneMatch["timezone"]["client"]) && $timezoneMatch["enable"] ? $timezoneMatch["timezone"]["client"] : $this->timezoneLocaleName;
            
            if ($hasTimestamp && $hasTime) {
                $date = DateTime::createFromFormat($from, $value, new DateTimeZone($clientTimezone));
                $date->setTimezone(new DateTimeZone($appTimezone));
                $formattedDate = $date->format($dateFormat);
                return $formattedDate;
            }
            
            $date = DateTime::createFromFormat($from, $value);
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

                return $defaultReturn($column, $formatDateLocale, $value);
            case "num":
            case "num-fmt":
                if (is_numeric($value)) {
                    return floatval($value);
                }
                return $value;
            case "integer":
            case "int":
                if (is_numeric($value)) {
                    return intval($value);
                }
                return $value;
            default:
                return $value;
        }
    }

    private function makeQuery($type, $condition, $column, $columnDT = null) {
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
                
                $timezoneMatch = null;
                if (
                    isset($this->timezone[$columnDT]) &&
                    isset($this->timezone[$columnDT]['format'], $this->timezone[$columnDT]['format']['sql'])
                ) {
                    $timezoneMatch = $this->timezone[$columnDT];
                } elseif (
                    isset($this->timezone[$column]) &&
                    isset($this->timezone[$column]['format'], $this->timezone[$column]['format']['sql'])
                ) {
                    $timezoneMatch = $this->timezone[$column];
                }

                if (is_null($query)) {
                    switch (strtolower(config('database.default'))) {
                        case 'sqlite':
                            $dateFormat = !empty($timezoneMatch) ? $timezoneMatch["format"]["sql"] : '%Y-%m-%d %H:%M';
                            $query = " ? {$condition} strftime('{$dateFormat}', {$column}) ";
                            break;
                        case 'pgsql':
                            $dateFormat = !empty($timezoneMatch) ? $timezoneMatch["format"]["sql"] : 'YYYY-MM-DD HH24:MI';
                            $query = " ? {$condition} to_char({$column}, '{$dateFormat}') ";

                            if (1 === count(explode(' ', $dateFormat))) {
                                $clientTimezone = !empty($timezoneMatch) && !empty($timezoneMatch["timezone"]["client"]) && $timezoneMatch["enable"] ? $timezoneMatch["timezone"]["client"] : $this->timezoneLocaleName;
                                $appTimezone    = !empty($timezoneMatch) && !empty($timezoneMatch["timezone"]["app"]) && $timezoneMatch["enable"] ? $timezoneMatch["timezone"]["app"] : $this->timezoneAppName;
                                $query = " ? {$condition} to_char({$column} AT TIME ZONE '{$appTimezone}' AT TIME ZONE '{$clientTimezone}', '{$dateFormat}') ";
                            }

                            break;
                        case 'mysql':
                        case 'mariadb':
                            $dateFormat = !empty($timezoneMatch) ? $timezoneMatch["format"]["sql"] : '%Y-%m-%d %H:%i';
                            $query = " ? {$condition} DATE_FORMAT({$column}, '{$dateFormat}') ";

                            if (1 === count(explode(' ', $dateFormat))) {
                                $clientTimezone = !empty($timezoneMatch) && !empty($timezoneMatch["timezone"]["client"]) && $timezoneMatch["enable"] ? $timezoneMatch["timezone"]["client"] : $this->timezoneLocaleName;
                                $appTimezone    = !empty($timezoneMatch) && !empty($timezoneMatch["timezone"]["app"]) && $timezoneMatch["enable"] ? $timezoneMatch["timezone"]["app"] : $this->timezoneAppName;
                                $query = " ? {$condition} DATE_FORMAT(CONVERT_TZ({$column}, '{$appTimezone}', '{$clientTimezone}'), '{$dateFormat}') ";
                            } 
                            break;
                        case 'sqlsrv':
                            $dateFormat = !empty($timezoneMatch) ? $timezoneMatch["format"]["sql"] : 'yyyy-MM-dd HH:mm';
                            $query = " ? {$condition} FORMAT({$column}, '{$dateFormat}') ";

                            if (1 === count(explode(' ', $dateFormat))) {
                                $clientTimezone = !empty($timezoneMatch) && !empty($timezoneMatch["timezone"]["client"]) && $timezoneMatch["enable"] ? $timezoneMatch["timezone"]["client"] : $this->timezoneLocaleName;
                                $appTimezone    = !empty($timezoneMatch) && !empty($timezoneMatch["timezone"]["app"]) && $timezoneMatch["enable"] ? $timezoneMatch["timezone"]["app"] : $this->timezoneAppName;
                                $dtApp = new \DateTime('now', new \DateTimeZone($appTimezone));
                                $dtClient = new \DateTime('now', new \DateTimeZone($clientTimezone));
                                $offset = ($dtClient->getOffset() - $dtApp->getOffset()) / 3600;

                                $query = " ? {$condition} FORMAT(DATEADD(HOUR, {$offset}, {$column}), '{$dateFormat}') ";
                            } 
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
                    '=', '!=', '<', '<=', '>', '>='  => " ? {$condition} {$column} ",
                    default => throw new \InvalidArgumentException("Condition '{$condition}' is not supported.")
                };
                break;
        }
        return $query;
    }

    private function makeParams($type, $condition, $column, $conditionDT, $param) {
        $params = [];
        $typeDate = in_array(strtolower($type), ['date', 'moment']);
        
        if (!is_array($param)) {
            $param = [$param];
        }

        switch (strtolower($condition)) {
            case 'between':
            case '!between':
                $params[] = $this->formatValue($column, $conditionDT, $param[0], $type) . (($typeDate) ? ":00" : '');
                $params[] = $this->formatValue($column, $conditionDT, $param[1], $type) . (($typeDate) ? ":59" : '');
                break;
            case 'starts':
            case '!starts':
                $params[] = "{$this->formatValue($column, $conditionDT, $conditionDT, $param[0], $type)}%";
                break;
            case 'contains':
            case '!contains':
                $params[] = "%{$this->formatValue($column, $conditionDT, $param[0], $type)}%";
                break;
            case 'ends':
            case '!ends':
                $params[] = "%{$this->formatValue($column, $conditionDT, $param[0], $type)}";
                break;

            case '=':
            case '!=':
            case '<':
            case '<=':
            case '>':
            case '>=':
                $params[] = $this->formatValue($column, $conditionDT, $param[0], $type);
                break;
            default:
                throw new \InvalidArgumentException("Condition '{$condition}' is not supported.");
        }
        return $params;
    }

    private function _matchConditional($condition, $column, $param, $type = 'string', $columnDT = null) :array {
        if (empty($column) || (!in_array(strtolower($condition), ['null', '!null']) && (empty($param) || is_null($param[0]) || $param[0] == ' ' || $param[0] == '' ))) return [null, null];
        if (in_array(strtolower($condition), ['between', '!between']) && (empty($param[0]) || empty($param[1]))) return [null, null];

        $query = $this->makeQuery($type, $condition, $column, $columnDT);
        $params = [];
        if (!in_array($condition, ['null', '!null'])) {
            $params = $this->makeParams($type, $condition, $column, $columnDT, $param);
        }
        
        return [$query, $params];
    }

    /**
     * The function is deprecated. The clause is now provided in the third parameter of the column query customization closure.
     *
     * @deprecated The function is deprecated. The clause is now provided in the third parameter of the column query customization closure.
     */
    public static function matchCondiction($condition, $column, $param, $type = 'string', $columnDT = null) :array {
        return (new self(request()))->_matchConditional($condition, $column, $param, $type, $columnDT);
    }
}
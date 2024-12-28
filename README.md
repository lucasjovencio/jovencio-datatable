
# Jovencio DataTable 📝  
Esta biblioteca é uma biblioteca para facilitar o retorno de um response para ajax datatable.
     
## Features  
  - Suporta [SearchBuilder.Criteria](https://datatables.net/extensions/searchbuilder) 
    em até 3 níveis.
  - Ordenação de colunas por ajax do datatable
  - Pesquisa em colunas por ajax do datatable

## Install

```sh
composer require jovencio/datatable
```

## Front-end
- Vue.js [Package](https://www.npmjs.com/package/jovencio-datatable-vue) 

  

## Usage/Examples
- [Unit Test](https://github.com/lucasjovencio/jovencio-datatable/blob/main/tests/Unit/DataTableQueryFactoryBuildTest.php) 
  
```php
<?php 
namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Jovencio\DataTable\DataTableQueryFactory;

class ExampleController extends Controller {
    public function index(DataTableQueryFactory $dataTableQueryFactory){
        $response = $dataTableQueryFactory->build(Model::class, [
            'query' => [
                'user_id' => function($criteria, $tableName, $matchConditional) {
                    list($queryParam, $params) = $matchConditional($criteria['condition'], 'user.name', $criteria['value'], $criteria['type']);
                    return (!empty($queryParam)) ? [" ( EXISTS ( SELECT 1 FROM users user WHERE user.id = {$tableName}.user_id and ({$queryParam}) ) ) ", $params] : null;
                }
            ],
            'with' => ['user'],
            'select' => ["id", "user_id", "name", "created_at"],
            'map' => function($row) {
                return [
                    'id' => $row->id,
                    'name' => $row->name,
                    'created_at' => $row->created_at,
                    'user_id' => [
                        'name' => $row->user->name,
                        'avatar' => $row->user->avatar,
                    ],
                ];
            },
            "timezone" => [
                "created_at" => [
                    "enable" => true,
                    "utc" => "UTC",
                    "date_format" => [
                        "php" => "Y-m-d",
                        "sql" => "%Y-%m-%d"
                    ]
                ]
            ],
            "where" => function($query) {
                return $query->where("users_id", 1);
            }
        ]);

        return response()->json($response);
    }
}
```

# Configurações do build

```
Este array de callbacks tem a responsabilidade de customizar querys para colunas que fazem referência para uma entidade estrangeira. Nesse cenário, onde uma tabela tem relacionamento com a tabela user, é feita uma pesquisa no datatable relacionado com a coluna user no frontend. Como não se trata da mesma entidade, é feita uma subconsulta na model correspondente com a adição da query que é gerada por $matchConditional.

'query' => [
    'user_id' => function($criteria, $tableName, $matchConditional) {
        list($queryParam, $params) = $matchConditional($criteria['condition'], 'user.name', $criteria['value'], $criteria['type']);
        return (!empty($queryParam)) ? [" ( EXISTS ( SELECT 1 FROM users user WHERE user.id = {$tableName}.user_id and ({$queryParam}) ) ) ", $params] : null;
    }
]

Este array pode ser feito com strings de relacionamento ou callbacks de with. Veja mais em [Laravel Relationships](https://laravel.com/docs/11.x/eloquent-relationships#automatically-hydrating-parent-models-on-children).
'with' => ['user']

Este array pode ser feito para filtrar os dados a serem retornados. Quando não informado ou vazio, são retornadas todas as colunas.
'select' => ["id", "user_id", "name", "created_at"]

Este callback pode ser usado para formatar o retorno do JSON. Quando não usado, é retornada toda a coleção sem tratamento, respeitando as regras do Model.
'map' => function($row) {
    return [
        'id' => $row->id,
        'name' => $row->name,
        'created_at' => $row->created_at,
        'user_id' => [
            'name' => $row->user->name,
            'avatar' => $row->user->avatar,
        ],
    ];
}

Este array pode ser usado para trabalhar com colunas de datas, formatando-as para comparação correta e aplicando timezone. O timezone é aplicado na data recebida, passando a data com o timezone correto para ser feita a consulta no banco de dados. Quando não passado um timezone específico, desabilitado a condição enable ou a inexistência da coluna, será considerada uma formatação padrão de Y-m-d H:i, e será aplicado o timezone padrão da sua aplicação na data recebida.
"timezone" => [
    "created_at" => [
        "enable" => true,
        "utc" => "UTC",
        "date_format" => [
            "php" => "Y-m-d",
            "sql" => "%Y-%m-%d"
        ]
    ]
]

Esta clausure é para condicional a ser feita por fora do searchbuilder do datatable.
"where" => function($query) {
    return $query->where("users_id", 1);
}
Este array de callbacks tem a responsabilidade de customizar querys para colunas que fazem referência para uma entidade estrangeira. Nesse cenário, onde uma tabela tem relacionamento com a tabela user, é feita uma pesquisa no datatable relacionado com a coluna user no frontend. Como não se trata da mesma entidade, é feita uma subconsulta na model correspondente com a adição da query que é gerada por $matchConditional.
```


# Build Configuration

## Query Customization for Foreign Entity Columns

This array of callbacks customizes queries for columns referencing a foreign entity. In scenarios where a table has a relationship with the `user` table, a search is performed in the datatable related to the `user` column in the frontend. Since it is not the same entity, a subquery is executed on the corresponding model with the addition of the query generated by `$matchConditional`.

```php
'query' => [
    'user_id' => function($criteria, $tableName, $matchConditional) {
        list($queryParam, $params) = $matchConditional($criteria['condition'], 'user.name', $criteria['value'], $criteria['type']);
        return (!empty($queryParam)) ? [" ( EXISTS ( SELECT 1 FROM users user WHERE user.id = {$tableName}.user_id and ({$queryParam}) ) ) ", $params] : null;
    }
]
```

---

## Relationship Loading

This array can include relationship strings or `with` callbacks. For more details, refer to the [Laravel Relationships Documentation](https://laravel.com/docs/11.x/eloquent-relationships#automatically-hydrating-parent-models-on-children).

```php
'with' => ['user']
```

---

## Column Selection

This array filters the data to be returned. If not provided or left empty, all columns are returned by default.

```php
'select' => ["id", "user_id", "name", "created_at"]
```

---

## JSON Response Formatting

This callback can format the JSON response. If not used, the entire collection is returned without formatting, respecting the model's rules.

```php
'map' => function($row) {
    return [
        'id' => $row->id,
        'name' => $row->name,
        'created_at' => $row->created_at,
        'user_id' => [
            'name' => $row->user->name,
            'avatar' => $row->user->avatar,
        ],
    ];
}
```

---

## Date Column Handling

This array formats date columns for correct comparison and applies timezone adjustments. The timezone is applied to the received date, ensuring the correct timezone is used for database queries. If no specific timezone is provided, or if the condition `enable` is disabled, or the column does not exist, a default format of `Y-m-d H:i` and the application's default timezone will be applied.

```php
"timezone" => [
    "created_at" => [
        "enable" => true,
        "utc" => "UTC",
        "date_format" => [
            "php" => "Y-m-d",
            "sql" => "%Y-%m-%d"
        ]
    ]
]
```

---

## Additional Where Clause

This closure allows conditional clauses to be applied outside of the datatable's search builder.

```php
"where" => function($query) {
    return $query->where("users_id", 1);
}
```

---

## Notes

- **Query Customization**: Callbacks provide the flexibility to define specific conditions for foreign entity queries.
- **Relationship Loading**: Use the `with` array for eager loading related models.
- **Column Selection**: Simplifies the data returned by the query, optimizing performance.
- **Response Formatting**: Ensures consistent JSON response structure.
- **Timezone Handling**: Maintains accuracy in date comparisons across different timezones.
- **Additional Conditions**: Custom `where` closures allow for tailored query logic beyond the default builder's scope.


## Contribution

Contributions are welcome! Please see the [CONTRIBUTING.md](CONTRIBUTING.md) file for more details.

## License

This project is licensed under the MIT License. See the [LICENSE](LICENSE) file for more details.
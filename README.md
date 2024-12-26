
  # Jovencio DataTable 📝  
  Esta biblioteca é uma biblioteca para facilitar o retorno de um response para ajax datatable.
     
  ## Features  
  - Suporta [SearchBuilder.Criteria](https://datatables.net/extensions/searchbuilder) 
    em até 3 níveis.
  - Ordenação de colunas por ajax do datatable
  - Pesquisa em colunas por ajax do datatable

  ## Front-end
  - Vue.js [Package](https://www.npmjs.com/package/jovencio-datatable-vue) 

  ## Usage/Examples  
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
                        list($queryParam, $params) = $matchConditional($criteria['condition'], 'user.name', $criteria['value']);
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

```
Este array de callbacks tem a responsabilidade de customizar querys para colunas que fazem referencia
para uma entidade estrageira, nesse cenário, onde uma tabela tem relacionamento com a tabela user, é feito uma
pesquisa no datatable relacionado com a coluna user no frontend, como não se trata da mesma entidade
é feito uma sub consulta na model correspondente com o a adição da query que é gerado por $matchConditional.

'query' => [
    'user_id' => function($criteria, $tableName, $matchConditional) {
        list($queryParam, $params) = $matchConditional($criteria['condition'], 'user.name', $criteria['value']);
        return (!empty($queryParam)) ? [" ( EXISTS ( SELECT 1 FROM users user WHERE user.id = {$tableName}.user_id and ({$queryParam}) ) ) ", $params] : null;
    }
]

Este array pode ser feito com strings de relacionamento ou callbacks de with https://laravel.com/docs/11.x/eloquent-relationships#automatically-hydrating-parent-models-on-children
'with' => ['user']

Este array pode ser feito para filtra os dados a serem retornados, quando não informado ou vazio é retornado todas as colunas
'select' => ["id", "user_id", "name", "created_at"],

Este callback pode ser usado para formatar o retorno do json, quando não usado é retornado toda collection sem tratamento, respeitando as regras do Model
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

Este array pode ser usado para trabalhar com colunas de datas, formatando a para comparação correta,
e aplicando timezone, o timezone é aplicado na data recebida, passando a data com o timezone correto
para ser feito a consulta no bando de dados, quando não passado um 
timezone especifico, desabilitado a condição enable ou a inexistencia da coluna, será considerado uma formatação padrão de
Y-m-d H:i, e será aplicado o timezone padrão da sua aplicação na data recebida.
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
```
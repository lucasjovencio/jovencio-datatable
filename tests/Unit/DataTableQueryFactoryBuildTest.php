<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Jovencio\DataTable\DataTableQueryFactory;
use Illuminate\Http\Request;
use Illuminate\Database\Capsule\Manager as Capsule;
use Jovencio\Tests\Models\UserTest;
use Jovencio\Tests\Models\PostTest;
use Faker\Factory as Faker;

if (!function_exists('config')) {
    function config($key = null)
    {
        if ($key == 'app.timezone') return 'UTC';
        if ($key == 'app.locale') return 'en';
        if ($key == 'database.default') return 'sqlite';
    }
}

class DataTableQueryFactoryBuildTest extends TestCase
{

    protected static Capsule $capsule;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::$capsule = new Capsule;
        self::$capsule->addConnection([
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        // self::$capsule->addConnection([
        //     'driver'    => 'mysql',
        //     'host'      => '127.0.0.1',
        //     'database'  => 'teste_unit',
        //     'username'  => 'teste_unit',
        //     'password'  => 'teste_unit',
        //     'charset'   => 'utf8mb4',
        //     'collation' => 'utf8mb4_unicode_ci',
        //     'prefix'    => '',
        // ]);

        self::$capsule->setAsGlobal();
        self::$capsule->bootEloquent();

        self::migrate();
        self::insertTestData();
    }

    protected static function migrate()
    {
        self::$capsule->schema()->create('user_tests', function ($table) {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->string('password');
            $table->datetime('email_verified_at')->nullable();
            $table->timestamps();
        });

        

        self::$capsule->schema()->create('post_tests', function ($table) {
            $table->id();
            $table->integer('user_test_id')->unsigned()->index();
            $table->string('title');
            $table->text('content');
            $table->foreign('user_test_id')->references('id')->on('user_tests')->onDelete('cascade');
            $table->timestamps();
        });
    }

    protected static function insertTestData()
    {
        $faker = Faker::create();
        $users = [];

        $id1 = random_int(1, 19);
        $id2 = random_int(20, 39);
        $id3 = random_int(40, 69);

        $posts = [
            [
                'user_test_id' => $id1+1,
                'title' => "vue ".$faker->sentence(3),
                'content' => "vue ".$faker->paragraph(3),
            ],
            [
                'user_test_id' => $id2+1,
                'title' => "laravel ".$faker->sentence(3),
                'content' => "laravel ".$faker->paragraph(3),
            ],
            [
                'user_test_id' => $id3+1,
                'title' => "jovencio ".$faker->sentence(3),
                'content' => "jovencio ".$faker->paragraph(3),
            ]
        ];
        
        $names[$id1] = "Lucas Jovencio";
        $names[$id2] = "Alfred";
        $names[$id3] = "Ted";

        $emails[$id1] = "fenix@email.com";
        $emails[$id2] = "marte@email.com";
        $emails[$id3] = "venus@email.com";

        $verified[$id1] = "2024-12-28 02:48:00";
        $verified[$id2] = date('Y-m-d H:i:s');
        $verified[$id3] = date('Y-m-d H:i:s');


        for ($i = 0; $i < 100; $i++) {
            $users[] = [
                'name' => (isset($names[$i])) ? $names[$i] : $faker->name,
                'email' => (isset($emails[$i])) ? $emails[$i] : $faker->unique()->safeEmail,
                'password' => \Illuminate\Support\Str::random(10),
                'email_verified_at' => (isset($verified[$i])) ? $verified[$i] : ((random_int(1, 10) % 2 == 0) ? date('Y-m-d H:i:s') : null)
            ];

            for ($iP = 0; $iP < random_int(0, 2); $iP++) {
                $posts[] = [
                    'title' => $faker->sentence(6),
                    'content' => $faker->paragraph(5),
                    'user_test_id' => $i + 1,
                ];

            }
        }

        self::$capsule->table('user_tests')->insert($users);
        self::$capsule->table('post_tests')->insert($posts);
    }

    public function testIWouldLikeASimpleUsageOfTheLibrary()
    {
        $request = new Request;
        $dataTableQueryFactory = new DataTableQueryFactory($request);
        $result = $dataTableQueryFactory->build(UserTest::class);
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('draw', $result);
        $this->assertArrayHasKey('recordsTotal', $result);
        $this->assertArrayHasKey('recordsFiltered', $result);
        $this->assertArrayHasKey('data', $result);
    }
    
    public function testIWantToVerifyTheCorrectReturnOfTheBuildMethod()
    {
        $request = new Request;
        $request->merge([
            "draw" => 1,
            "columns" => [
                [
                    "data" => "id",
                    "name" => "",
                    "searchable" => false,
                    "orderable" => true,
                    "search" => [
                        "value" => "",
                        "regex" => false
                    ]
                ],
                [
                    "data" => "name",
                    "name" => "",
                    "searchable" => true,
                    "orderable" => true,
                    "search" => [
                        "value" => "",
                        "regex" => false
                    ]
                ],
                [
                    "data" => "email",
                    "name" => "",
                    "searchable" => true,
                    "orderable" => true,
                    "search" => [
                        "value" => "",
                        "regex" => false
                    ]
                ],
                [
                    "data" => "quantity_post",
                    "name" => "",
                    "searchable" => false,
                    "orderable" => false,
                    "search" => [
                        "value" => "",
                        "regex" => false
                    ]
                ],
                [
                    "data" => "created_at",
                    "name" => "",
                    "searchable" => false,
                    "orderable" => true,
                    "search" => [
                        "value" => "",
                        "regex" => false
                    ]
                ],
                [
                    "data" => "actions",
                    "name" => "",
                    "searchable" => false,
                    "orderable" => false,
                    "search" => [
                        "value" => "",
                        "regex" => false
                    ]
                ]
            ],
            "order" => [
                [
                    "column" => 0,
                    "dir" => "asc",
                    "name" => ""
                ]
            ],
            "start" => 0,
            "length" => 10,
            "search" => [
                "value" => "",
                "regex" => false
            ],
            "format_date_locale" => "DD/MM/YYYY HH:mm",
            "timezone_locale" => "America/Sao_Paulo"
        ]);
                
        $dataTableQueryFactory = new DataTableQueryFactory($request);
        $config = [
            'query' => [],
            'with' => ["posts"],
            'select' => ["id", "name", "email", "created_at"],
            'map' => fn($user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'created_at' => $user->created_at,
                'quantity_post' => $user->posts->count(),
            ],
            'timezone' => [],
            'where' => null
        ];

        $result = $dataTableQueryFactory->build(UserTest::class, $config);
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('draw', $result);
        $this->assertArrayHasKey('recordsTotal', $result);
        $this->assertArrayHasKey('recordsFiltered', $result);
        $this->assertArrayHasKey('data', $result);
    }

    public function testIWantToSortInDescendingOrderAndTheFirstPositionShouldHaveTheId100()
    {
        $request = new Request;
        $request->merge([
            "draw" => 1,
            "columns" => [
                [
                    "data" => "id",
                    "name" => "",
                    "searchable" => false,
                    "orderable" => true,
                    "search" => [
                        "value" => "",
                        "regex" => false
                    ]
                ],
                [
                    "data" => "name",
                    "name" => "",
                    "searchable" => true,
                    "orderable" => true,
                    "search" => [
                        "value" => "",
                        "regex" => false
                    ]
                ],
                [
                    "data" => "email",
                    "name" => "",
                    "searchable" => true,
                    "orderable" => true,
                    "search" => [
                        "value" => "",
                        "regex" => false
                    ]
                ],
                [
                    "data" => "quantity_post",
                    "name" => "",
                    "searchable" => false,
                    "orderable" => false,
                    "search" => [
                        "value" => "",
                        "regex" => false
                    ]
                ],
                [
                    "data" => "created_at",
                    "name" => "",
                    "searchable" => false,
                    "orderable" => true,
                    "search" => [
                        "value" => "",
                        "regex" => false
                    ]
                ],
                [
                    "data" => "actions",
                    "name" => "",
                    "searchable" => false,
                    "orderable" => false,
                    "search" => [
                        "value" => "",
                        "regex" => false
                    ]
                ]
            ],
            "order" => [
                [
                    "column" => 0,
                    "dir" => "desc",
                    "name" => "id"
                ]
            ],
            "start" => 0,
            "length" => 10,
            "search" => [
                "value" => "",
                "regex" => false
            ],
            "format_date_locale" => "DD/MM/YYYY HH:mm",
            "timezone_locale" => "America/Sao_Paulo"
        ]);
                
        $dataTableQueryFactory = new DataTableQueryFactory($request);
        $config = [
            'query' => [],
            'with' => ["posts"],
            'select' => ["id", "name", "email", "created_at"],
            'map' => fn($user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'created_at' => $user->created_at,
                'quantity_post' => $user->posts->count(),
            ],
            'timezone' => [],
            'where' => null
        ];

        $result = $dataTableQueryFactory->build(UserTest::class, $config);
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('data', $result);
        $this->assertNotEmpty($result['data']);
        $this->assertSame($result['data'][0]['id'], 100);
    }

    public function testIWantToSearchForUsersWithTheNameLucas()
    {
        $request = new Request;
        $request->merge([
            "draw" => 1,
            "columns" => [
                [
                    "data" => "id",
                    "name" => "",
                    "searchable" => false,
                    "orderable" => true,
                    "search" => [
                        "value" => "",
                        "regex" => false
                    ]
                ],
                [
                    "data" => "name",
                    "name" => "",
                    "searchable" => true,
                    "orderable" => true,
                    "search" => [
                        "value" => "",
                        "regex" => false
                    ]
                ],
                [
                    "data" => "email",
                    "name" => "",
                    "searchable" => true,
                    "orderable" => true,
                    "search" => [
                        "value" => "",
                        "regex" => false
                    ]
                ],
                [
                    "data" => "email_verified_at",
                    "name" => "",
                    "searchable" => false,
                    "orderable" => false,
                    "search" => [
                        "value" => "",
                        "regex" => false
                    ]
                ],
                [
                    "data" => "quantity_post",
                    "name" => "",
                    "searchable" => false,
                    "orderable" => false,
                    "search" => [
                        "value" => "",
                        "regex" => false
                    ]
                ],
                [
                    "data" => "created_at",
                    "name" => "",
                    "searchable" => false,
                    "orderable" => true,
                    "search" => [
                        "value" => "",
                        "regex" => false
                    ]
                ],
                [
                    "data" => "actions",
                    "name" => "",
                    "searchable" => false,
                    "orderable" => false,
                    "search" => [
                        "value" => "",
                        "regex" => false
                    ]
                ]
            ],
            "order" => [
                [
                    "column" => 0,
                    "dir" => "desc",
                    "name" => "id"
                ]
            ],
            "start" => 0,
            "length" => 10,
            "search" => [
                "value" => "",
                "regex" => false
            ],
            "format_date_locale" => "DD/MM/YYYY HH:mm",
            "timezone_locale" => "America/Sao_Paulo",
            "searchBuilder" => [
                "criteria" => [
                    [
                        "condition" => "contains",
                        "data" => "Name",
                        "origData" => "name",
                        "type" => "string",
                        "value" => ["Lucas Jovencio"],
                        "value1" => "Lucas Jovencio"
                    ]
                ],
                "logic" => "AND"
            ]
        ]);
                
        $dataTableQueryFactory = new DataTableQueryFactory($request);
        $config = [
            'query' => [],
            'with' => ["posts"],
            'select' => ["id", "name", "email", "created_at"],
            'map' => fn($user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'created_at' => $user->created_at,
                'email_verified_at' => $user->email_verified_at,
                'quantity_post' => $user->posts->count(),
            ],
            'timezone' => [],
            'where' => null
        ];  

        $result = $dataTableQueryFactory->build(UserTest::class, $config);
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('data', $result);
        $this->assertNotEmpty($result['data']);
        $this->assertSame($result['data'][0]['name'], "Lucas Jovencio");
    }

    public function testIWantToSearchForUsersWithTheNameLucasOrAlfred()
    {
        $request = new Request;
        $request->merge([
            "draw" => 1,
            "columns" => [
                [
                    "data" => "id",
                    "name" => "",
                    "searchable" => false,
                    "orderable" => true,
                    "search" => [
                        "value" => "",
                        "regex" => false
                    ]
                ],
                [
                    "data" => "name",
                    "name" => "",
                    "searchable" => true,
                    "orderable" => true,
                    "search" => [
                        "value" => "",
                        "regex" => false
                    ]
                ],
                [
                    "data" => "email",
                    "name" => "",
                    "searchable" => true,
                    "orderable" => true,
                    "search" => [
                        "value" => "",
                        "regex" => false
                    ]
                ],
                [
                    "data" => "quantity_post",
                    "name" => "",
                    "searchable" => false,
                    "orderable" => false,
                    "search" => [
                        "value" => "",
                        "regex" => false
                    ]
                ],
                [
                    "data" => "created_at",
                    "name" => "",
                    "searchable" => false,
                    "orderable" => true,
                    "search" => [
                        "value" => "",
                        "regex" => false
                    ]
                ],
                [
                    "data" => "actions",
                    "name" => "",
                    "searchable" => false,
                    "orderable" => false,
                    "search" => [
                        "value" => "",
                        "regex" => false
                    ]
                ]
            ],
            "order" => [
                [
                    "column" => 0,
                    "dir" => "desc",
                    "name" => "id"
                ]
            ],
            "start" => 0,
            "length" => 10,
            "search" => [
                "value" => "",
                "regex" => false
            ],
            "format_date_locale" => "DD/MM/YYYY HH:mm",
            "timezone_locale" => "America/Sao_Paulo",
            "searchBuilder" => [
                "criteria" => [
                    [
                        "condition" => "contains",
                        "data" => "Name",
                        "origData" => "name",
                        "type" => "string",
                        "value" => ["Lucas Jovencio"],
                        "value1" => "Lucas Jovencio"
                    ],
                    [
                        "condition" => "=",
                        "data" => "Name",
                        "origData" => "name",
                        "type" => "string",
                        "value" => ["Alfred"],
                        "value1" => "Alfred"
                    ]
                ],
                "logic" => "OR"
            ]
        ]);
                
        $dataTableQueryFactory = new DataTableQueryFactory($request);
        $config = [
            'query' => [],
            'with' => ["posts"],
            'select' => ["id", "name", "email", "created_at"],
            'map' => fn($user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'created_at' => $user->created_at,
                'quantity_post' => $user->posts->count(),
            ],
            'timezone' => [],
            'where' => null
        ];

        $result = $dataTableQueryFactory->build(UserTest::class, $config);
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('data', $result);
        $this->assertNotEmpty($result['data']);
        $this->assertTrue(in_array($result['data'][0]['name'], ["Lucas Jovencio", "Alfred"]));
        $this->assertTrue(in_array($result['data'][1]['name'], ["Lucas Jovencio", "Alfred"]));
    }

    public function testIWantToSearchForUsersWithTheNameLucasOrAlfredButTheSecondValueWasNotProvidedAndReturnOnlyLucas()
    {
        $request = new Request;
        $request->merge([
            "draw" => 1,
            "columns" => [
                [
                    "data" => "id",
                    "name" => "",
                    "searchable" => false,
                    "orderable" => true,
                    "search" => [
                        "value" => "",
                        "regex" => false
                    ]
                ],
                [
                    "data" => "name",
                    "name" => "",
                    "searchable" => true,
                    "orderable" => true,
                    "search" => [
                        "value" => "",
                        "regex" => false
                    ]
                ],
                [
                    "data" => "email",
                    "name" => "",
                    "searchable" => true,
                    "orderable" => true,
                    "search" => [
                        "value" => "",
                        "regex" => false
                    ]
                ],
                [
                    "data" => "quantity_post",
                    "name" => "",
                    "searchable" => false,
                    "orderable" => false,
                    "search" => [
                        "value" => "",
                        "regex" => false
                    ]
                ],
                [
                    "data" => "created_at",
                    "name" => "",
                    "searchable" => false,
                    "orderable" => true,
                    "search" => [
                        "value" => "",
                        "regex" => false
                    ]
                ],
                [
                    "data" => "actions",
                    "name" => "",
                    "searchable" => false,
                    "orderable" => false,
                    "search" => [
                        "value" => "",
                        "regex" => false
                    ]
                ]
            ],
            "order" => [
                [
                    "column" => 0,
                    "dir" => "desc",
                    "name" => "id"
                ]
            ],
            "start" => 0,
            "length" => 10,
            "search" => [
                "value" => "",
                "regex" => false
            ],
            "format_date_locale" => "DD/MM/YYYY HH:mm",
            "timezone_locale" => "America/Sao_Paulo",
            "searchBuilder" => [
                "criteria" => [
                    [
                        "condition" => "contains",
                        "data" => "Name",
                        "origData" => "name",
                        "type" => "string",
                        "value" => ["Lucas Jovencio"],
                        "value1" => "Lucas Jovencio"
                    ],
                    [
                        "condition" => "=",
                        "data" => "Name",
                        "origData" => "name",
                        "type" => "string",
                        "value" => [""],
                        "value1" => ""
                    ]
                ],
                "logic" => "OR"
            ]
        ]);
                
        $dataTableQueryFactory = new DataTableQueryFactory($request);
        $config = [
            'query' => [],
            'with' => ["posts"],
            'select' => ["id", "name", "email", "created_at"],
            'map' => fn($user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'created_at' => $user->created_at,
                'quantity_post' => $user->posts->count(),
            ],
            'timezone' => [],
            'where' => null
        ];

        $result = $dataTableQueryFactory->build(UserTest::class, $config);
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('data', $result);
        $this->assertNotEmpty($result['data']);
        $this->assertSame($result['data'][0]['name'], "Lucas Jovencio");
    }

    public function testIWantAQueryWithThreeChainedWhereClauses()
    {
        $request = new Request;
        $request->merge([
            "draw" => 1,
            "columns" => [
                [
                    "data" => "id",
                    "name" => "",
                    "searchable" => false,
                    "orderable" => true,
                    "search" => [
                        "value" => "",
                        "regex" => false
                    ]
                ],
                [
                    "data" => "name",
                    "name" => "",
                    "searchable" => true,
                    "orderable" => true,
                    "search" => [
                        "value" => "",
                        "regex" => false
                    ]
                ],
                [
                    "data" => "email",
                    "name" => "",
                    "searchable" => true,
                    "orderable" => true,
                    "search" => [
                        "value" => "",
                        "regex" => false
                    ]
                ],
                [
                    "data" => "email_verified_at",
                    "name" => "",
                    "searchable" => false,
                    "orderable" => false,
                    "search" => [
                        "value" => "",
                        "regex" => false
                    ]
                ],
                [
                    "data" => "quantity_post",
                    "name" => "",
                    "searchable" => false,
                    "orderable" => false,
                    "search" => [
                        "value" => "",
                        "regex" => false
                    ]
                ],
                [
                    "data" => "created_at",
                    "name" => "",
                    "searchable" => false,
                    "orderable" => true,
                    "search" => [
                        "value" => "",
                        "regex" => false
                    ]
                ],
                [
                    "data" => "actions",
                    "name" => "",
                    "searchable" => false,
                    "orderable" => false,
                    "search" => [
                        "value" => "",
                        "regex" => false
                    ]
                ]
            ],
            "order" => [
                [
                    "column" => 0,
                    "dir" => "desc",
                    "name" => "id"
                ]
            ],
            "start" => 0,
            "length" => 10,
            "search" => [
                "value" => "",
                "regex" => false
            ],
            "format_date_locale" => "MM/DD/YYYY HH:mm A",
            "timezone_locale" => "UTC",
            'searchBuilder' => [
                'criteria' => [
                    [
                        'condition' => '=',
                        'data' => 'Name',
                        'origData' => 'name',
                        'type' => 'string',
                        'value' => ['Lucas Jovencio'],
                        'value1' => 'Lucas Jovencio'
                    ],
                    [
                        'criteria' => [
                            [
                                'condition' => '>',
                                'data' => 'Email veried at',
                                'origData' => 'email_verified_at',
                                'type' => 'moment',
                                'value' => ['01/01/'.date('Y').' 12:00 AM'],
                                'value1' => '01/01/'.date('Y').' 12:00 AM'
                            ],
                            [
                                'condition' => '<',
                                'data' => 'Email veried at',
                                'origData' => 'email_verified_at',
                                'type' => 'moment',
                                'value' => ['12/31/2100 12:00 AM'],
                                'value1' => '12/31/2100 12:00 AM'
                            ],
                            [
                                'criteria' => [
                                    [
                                        'condition' => '=',
                                        'data' => 'E-mail',
                                        'origData' => 'email',
                                        'type' => 'string',
                                        'value' => ['fenix@email.com'],
                                        'value1' => 'fenix@email.com'
                                    ]
                                ],
                                'logic' => 'AND'
                            ]
                        ],
                        'logic' => 'AND'
                    ]
                ],
                'logic' => 'AND'
            ]
        ]);
                
        $dataTableQueryFactory = new DataTableQueryFactory($request);
        $config = [
            'query' => [],
            'with' => ["posts"],
            'select' => ["id", "name", "email", "created_at"],
            'map' => fn($user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'created_at' => $user->created_at,
                'quantity_post' => $user->posts->count(),
            ],
            'timezone' => [],
            'where' => null
        ];

        $result = $dataTableQueryFactory->build(UserTest::class, $config);
      
        $this->assertIsArray($result);
        $this->assertArrayHasKey('data', $result);
        $this->assertNotEmpty($result['data']);
        $this->assertSame($result['data'][0]['name'], "Lucas Jovencio");
    }

    public function testWithAQueryConsideringTheBrazilianTimezoneIWantItToTeturnTheCorrectUser()
    {
        $request = new Request;
        $request->merge([
            "draw" => 1,
            "columns" => [
                [
                    "data" => "id",
                    "name" => "",
                    "searchable" => false,
                    "orderable" => true,
                    "search" => [
                        "value" => "",
                        "regex" => false
                    ]
                ],
                [
                    "data" => "name",
                    "name" => "",
                    "searchable" => true,
                    "orderable" => true,
                    "search" => [
                        "value" => "",
                        "regex" => false
                    ]
                ],
                [
                    "data" => "email",
                    "name" => "",
                    "searchable" => true,
                    "orderable" => true,
                    "search" => [
                        "value" => "",
                        "regex" => false
                    ]
                ],
                [
                    "data" => "email_verified_at",
                    "name" => "",
                    "searchable" => false,
                    "orderable" => false,
                    "search" => [
                        "value" => "",
                        "regex" => false
                    ]
                ],
                [
                    "data" => "quantity_post",
                    "name" => "",
                    "searchable" => false,
                    "orderable" => false,
                    "search" => [
                        "value" => "",
                        "regex" => false
                    ]
                ],
                [
                    "data" => "created_at",
                    "name" => "",
                    "searchable" => false,
                    "orderable" => true,
                    "search" => [
                        "value" => "",
                        "regex" => false
                    ]
                ],
                [
                    "data" => "actions",
                    "name" => "",
                    "searchable" => false,
                    "orderable" => false,
                    "search" => [
                        "value" => "",
                        "regex" => false
                    ]
                ]
            ],
            "order" => [
                [
                    "column" => 0,
                    "dir" => "desc",
                    "name" => "id"
                ]
            ],
            "start" => 0,
            "length" => 10,
            "search" => [
                "value" => "",
                "regex" => false
            ],
            "format_date_locale" => "DD/MM/YYYY HH:mm",
            "timezone_locale" => "America/Sao_Paulo",
            'searchBuilder' => [
                'criteria' => [
                    [
                        'condition' => '=',
                        'data' => 'Name',
                        'origData' => 'name',
                        'type' => 'string',
                        'value' => ['Lucas Jovencio'],
                        'value1' => 'Lucas Jovencio'
                    ],
                    [
                        'criteria' => [
                            [
                                'condition' => '=',
                                'data' => 'Email veried at',
                                'origData' => 'email_verified_at',
                                'type' => 'moment',
                                'value' => ['27/12/2024 23:48'],
                                'value1' => '27/12/2024 23:48'
                            ]
                        ],
                        'logic' => 'AND'
                    ]
                ],
                'logic' => 'AND'
            ]
        ]);
                
        $dataTableQueryFactory = new DataTableQueryFactory($request);
        $config = [
            'query' => [],
            'with' => ["posts"],
            'select' => ["id", "name", "email", "created_at"],
            'map' => fn($user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'created_at' => $user->created_at,
                'quantity_post' => $user->posts->count(),
            ],
            'timezone' => [],
            'where' => null
        ];

        $result = $dataTableQueryFactory->build(UserTest::class, $config);
      
        $this->assertIsArray($result);
        $this->assertArrayHasKey('data', $result);
        $this->assertNotEmpty($result['data']);
        $this->assertSame($result['data'][0]['name'], "Lucas Jovencio");
    }

    public function testIWantToUseTheTimezoneSettingFromTheConfig()
    {
        $request = new Request;
        $request->merge([
            "draw" => 1,
            "columns" => [
                [
                    "data" => "id",
                    "name" => "",
                    "searchable" => false,
                    "orderable" => true,
                    "search" => [
                        "value" => "",
                        "regex" => false
                    ]
                ],
                [
                    "data" => "name",
                    "name" => "",
                    "searchable" => true,
                    "orderable" => true,
                    "search" => [
                        "value" => "",
                        "regex" => false
                    ]
                ],
                [
                    "data" => "email",
                    "name" => "",
                    "searchable" => true,
                    "orderable" => true,
                    "search" => [
                        "value" => "",
                        "regex" => false
                    ]
                ],
                [
                    "data" => "email_verified_at",
                    "name" => "",
                    "searchable" => false,
                    "orderable" => false,
                    "search" => [
                        "value" => "",
                        "regex" => false
                    ]
                ],
                [
                    "data" => "quantity_post",
                    "name" => "",
                    "searchable" => false,
                    "orderable" => false,
                    "search" => [
                        "value" => "",
                        "regex" => false
                    ]
                ],
                [
                    "data" => "created_at",
                    "name" => "",
                    "searchable" => false,
                    "orderable" => true,
                    "search" => [
                        "value" => "",
                        "regex" => false
                    ]
                ],
                [
                    "data" => "actions",
                    "name" => "",
                    "searchable" => false,
                    "orderable" => false,
                    "search" => [
                        "value" => "",
                        "regex" => false
                    ]
                ]
            ],
            "order" => [
                [
                    "column" => 0,
                    "dir" => "desc",
                    "name" => "id"
                ]
            ],
            "start" => 0,
            "length" => 10,
            "search" => [
                "value" => "",
                "regex" => false
            ],
            "format_date_locale" => "DD/MM/YYYY HH:mm",
            "timezone_locale" => "America/Sao_Paulo",
            'searchBuilder' => [
                'criteria' => [
                    [
                        'condition' => '=',
                        'data' => 'Name',
                        'origData' => 'name',
                        'type' => 'string',
                        'value' => ['Lucas Jovencio'],
                        'value1' => 'Lucas Jovencio'
                    ],
                    [
                        'criteria' => [
                            [
                                'condition' => '=',
                                'data' => 'Email veried at',
                                'origData' => 'email_verified_at',
                                'type' => 'moment',
                                'value' => ['27/12/2024 23:48'],
                                'value1' => '27/12/2024 23:48'
                            ]
                        ],
                        'logic' => 'AND'
                    ]
                ],
                'logic' => 'AND'
            ]
        ]);
                
        $dataTableQueryFactory = new DataTableQueryFactory($request);
        $config = [
            'query' => [],
            'with' => ["posts"],
            'select' => ["id", "name", "email", "created_at"],
            'map' => fn($user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'created_at' => $user->created_at,
                'quantity_post' => $user->posts->count(),
            ],
            'timezone' => [
                "email_verified_at" => [
                    "enable" => true,
                    "utc" => "UTC",
                    "date_format" => [
                        "php" => "Y-m-d",
                        "sql" => "%Y-%m-%d"
                    ]
                ]
            ],
            'where' => null
        ];

        $result = $dataTableQueryFactory->build(UserTest::class, $config);
      
        $this->assertIsArray($result);
        $this->assertArrayHasKey('data', $result);
        $this->assertNotEmpty($result['data']);
        $this->assertSame($result['data'][0]['name'], "Lucas Jovencio");
    }

    
    public function testIWantToUseACustomWhereConfigurationOutsideTheScopeOfTheDatatable()
    {
        $request = new Request;
        $request->merge([
            "draw" => 1,
            "columns" => [
                [
                    "data" => "id",
                    "name" => "",
                    "searchable" => false,
                    "orderable" => true,
                    "search" => [
                        "value" => "",
                        "regex" => false
                    ]
                ],
                [
                    "data" => "name",
                    "name" => "",
                    "searchable" => true,
                    "orderable" => true,
                    "search" => [
                        "value" => "",
                        "regex" => false
                    ]
                ],
                [
                    "data" => "email",
                    "name" => "",
                    "searchable" => true,
                    "orderable" => true,
                    "search" => [
                        "value" => "",
                        "regex" => false
                    ]
                ],
                [
                    "data" => "quantity_post",
                    "name" => "",
                    "searchable" => false,
                    "orderable" => false,
                    "search" => [
                        "value" => "",
                        "regex" => false
                    ]
                ],
                [
                    "data" => "created_at",
                    "name" => "",
                    "searchable" => false,
                    "orderable" => true,
                    "search" => [
                        "value" => "",
                        "regex" => false
                    ]
                ],
                [
                    "data" => "actions",
                    "name" => "",
                    "searchable" => false,
                    "orderable" => false,
                    "search" => [
                        "value" => "",
                        "regex" => false
                    ]
                ]
            ],
            "order" => [
                [
                    "column" => 0,
                    "dir" => "desc",
                    "name" => "id"
                ]
            ],
            "start" => 0,
            "length" => 10,
            "search" => [
                "value" => "",
                "regex" => false
            ],
            "format_date_locale" => "DD/MM/YYYY HH:mm",
            "timezone_locale" => "America/Sao_Paulo"
        ]);

        $dataTableQueryFactory = new DataTableQueryFactory($request);
        $config = [
            'query' => [],
            'with' => ["posts"],
            'select' => ["id", "name", "email", "created_at"],
            'map' => fn($user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'created_at' => $user->created_at,
                'quantity_post' => $user->posts->count(),
            ],
            'timezone' => [],
            'where' => fn ($query) => $query->where('name', 'Lucas Jovencio')
        ];

        $result = $dataTableQueryFactory->build(UserTest::class, $config);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('data', $result);
        $this->assertNotEmpty($result['data']);
        $this->assertTrue(in_array($result['data'][0]['name'], ["Lucas Jovencio"]));
    }

    /**
     * testIWantAllUsersWhoHaveAtLeastOnePost
     * testIWantAllUsersWhoHaveNoPost
     * 
     * dont't work with sqlite
    */
    // public function testIWantAllUsersWhoHaveAtLeastOnePost()
    // {
    //     $request = new Request;
    //     $request->merge([
    //         "draw" => 1,
    //         "columns" => [
    //             [
    //                 "data" => "id",
    //                 "name" => "",
    //                 "searchable" => false,
    //                 "orderable" => true,
    //                 "search" => [
    //                     "value" => "",
    //                     "regex" => false
    //                 ]
    //             ],
    //             [
    //                 "data" => "name",
    //                 "name" => "",
    //                 "searchable" => true,
    //                 "orderable" => true,
    //                 "search" => [
    //                     "value" => "",
    //                     "regex" => false
    //                 ]
    //             ],
    //             [
    //                 "data" => "email",
    //                 "name" => "",
    //                 "searchable" => true,
    //                 "orderable" => true,
    //                 "search" => [
    //                     "value" => "",
    //                     "regex" => false
    //                 ]
    //             ],
    //             [
    //                 "data" => "quantity_post",
    //                 "name" => "",
    //                 "searchable" => false,
    //                 "orderable" => false,
    //                 "search" => [
    //                     "value" => "",
    //                     "regex" => false
    //                 ]
    //             ],
    //             [
    //                 "data" => "created_at",
    //                 "name" => "",
    //                 "searchable" => false,
    //                 "orderable" => true,
    //                 "search" => [
    //                     "value" => "",
    //                     "regex" => false
    //                 ]
    //             ],
    //             [
    //                 "data" => "actions",
    //                 "name" => "",
    //                 "searchable" => false,
    //                 "orderable" => false,
    //                 "search" => [
    //                     "value" => "",
    //                     "regex" => false
    //                 ]
    //             ]
    //         ],
    //         "order" => [
    //             [
    //                 "column" => 0,
    //                 "dir" => "asc",
    //                 "name" => ""
    //             ]
    //         ],
    //         "start" => 0,
    //         "length" => 10,
    //         "search" => [
    //             "value" => "",
    //             "regex" => false
    //         ],
    //         'searchBuilder' => [
    //             'criteria' => [
    //                 [
    //                     'condition' => '>=',
    //                     'data' => 'Quantity of posts',
    //                     'origData' => 'quantity_post',
    //                     'type' => 'num',
    //                     'value' => ['1'],
    //                     'value1' => '1'
    //                 ]
    //             ],
    //             'logic' => 'AND'
    //         ],
    //         "format_date_locale" => "DD/MM/YYYY HH:mm",
    //         "timezone_locale" => "America/Sao_Paulo"
    //     ]);

    //     $dataTableQueryFactory = new DataTableQueryFactory($request);
    //     $config = [
    //         'query' => [
    //             "quantity_post" => function($criteria, $tableName, $matchConditional) {
    //                 list($queryParam, $params) = $matchConditional($criteria['condition'], " (SELECT count(1) FROM post_tests WHERE post_tests.user_test_id = {$tableName}.id) ", $criteria['value'], $criteria['type']);
    //                 return (!empty($queryParam)) ? [$queryParam, $params] : null;
    //             }
    //         ],
    //         'with' => ["posts"],
    //         'select' => ["id", "name", "email", "created_at"],
    //         'map' => fn($user) => [
    //             'id' => $user->id,
    //             'name' => $user->name,
    //             'email' => $user->email,
    //             'created_at' => $user->created_at,
    //             'quantity_post' => $user->posts->count(),
    //         ],
    //     ];

    //     $result = $dataTableQueryFactory->build(UserTest::class, $config);

    //     $this->assertIsArray($result);
    //     $this->assertArrayHasKey('data', $result);
    //     $this->assertNotEmpty($result['data']);
    //     $this->assertTrue(count($result['data']) >= 0);
    // }

    // public function testIWantAllUsersWhoHaveNoPost()
    // {
    //     $request = new Request;
    //     $request->merge([
    //         "draw" => 1,
    //         "columns" => [
    //             [
    //                 "data" => "id",
    //                 "name" => "",
    //                 "searchable" => false,
    //                 "orderable" => true,
    //                 "search" => [
    //                     "value" => "",
    //                     "regex" => false
    //                 ]
    //             ],
    //             [
    //                 "data" => "name",
    //                 "name" => "",
    //                 "searchable" => true,
    //                 "orderable" => true,
    //                 "search" => [
    //                     "value" => "",
    //                     "regex" => false
    //                 ]
    //             ],
    //             [
    //                 "data" => "email",
    //                 "name" => "",
    //                 "searchable" => true,
    //                 "orderable" => true,
    //                 "search" => [
    //                     "value" => "",
    //                     "regex" => false
    //                 ]
    //             ],
    //             [
    //                 "data" => "quantity_post",
    //                 "name" => "",
    //                 "searchable" => false,
    //                 "orderable" => false,
    //                 "search" => [
    //                     "value" => "",
    //                     "regex" => false
    //                 ]
    //             ],
    //             [
    //                 "data" => "created_at",
    //                 "name" => "",
    //                 "searchable" => false,
    //                 "orderable" => true,
    //                 "search" => [
    //                     "value" => "",
    //                     "regex" => false
    //                 ]
    //             ],
    //             [
    //                 "data" => "actions",
    //                 "name" => "",
    //                 "searchable" => false,
    //                 "orderable" => false,
    //                 "search" => [
    //                     "value" => "",
    //                     "regex" => false
    //                 ]
    //             ]
    //         ],
    //         "order" => [
    //             [
    //                 "column" => 0,
    //                 "dir" => "asc",
    //                 "name" => ""
    //             ]
    //         ],
    //         "start" => 0,
    //         "length" => 10,
    //         "search" => [
    //             "value" => "",
    //             "regex" => false
    //         ],
    //         'searchBuilder' => [
    //             'criteria' => [
    //                 [
    //                     'condition' => '<=',
    //                     'data' => 'Quantity of posts',
    //                     'origData' => 'quantity_post',
    //                     'type' => 'num',
    //                     'value' => ['0'],
    //                     'value1' => '0'
    //                 ]
    //             ],
    //             'logic' => 'AND'
    //         ],
    //         "format_date_locale" => "DD/MM/YYYY HH:mm",
    //         "timezone_locale" => "America/Sao_Paulo"
    //     ]);

    //     $dataTableQueryFactory = new DataTableQueryFactory($request);
    //     $config = [
    //         'query' => [
    //             "quantity_post" => function($criteria, $tableName, $matchConditional) {
    //                 list($queryParam, $params) = $matchConditional($criteria['condition'], " (SELECT count(1) FROM post_tests WHERE post_tests.user_test_id = {$tableName}.id) ", $criteria['value'], $criteria['type']);
    //                 return (!empty($queryParam)) ? [$queryParam, $params] : null;
    //             }
    //         ],
    //         'with' => ["posts"],
    //         'select' => ["id", "name", "email", "created_at"],
    //         'map' => fn($user) => [
    //             'id' => $user->id,
    //             'name' => $user->name,
    //             'email' => $user->email,
    //             'created_at' => $user->created_at,
    //             'quantity_post' => $user->posts->count(),
    //         ],
    //     ];

    //     $result = $dataTableQueryFactory->build(UserTest::class, $config);

    //     $this->assertIsArray($result);
    //     $this->assertArrayHasKey('data', $result);
    //     $this->assertNotEmpty($result['data']);
    //     $this->assertTrue(count($result['data']) >= 0);
    // }

    public function testIWantAllPostFromLucas()
    {
        $request = new Request;
        $request->merge([
            "draw" => 1,
            "columns" => [
                [
                    "data" => "id",
                    "name" => "",
                    "searchable" => false,
                    "orderable" => true,
                    "search" => [
                        "value" => "",
                        "regex" => false
                    ]
                ],
                [
                    "data" => "title",
                    "name" => "",
                    "searchable" => true,
                    "orderable" => true,
                    "search" => [
                        "value" => "",
                        "regex" => false
                    ]
                ],
                [
                    "data" => "content",
                    "name" => "",
                    "searchable" => true,
                    "orderable" => true,
                    "search" => [
                        "value" => "",
                        "regex" => false
                    ]
                ],
                [
                    "data" => "user_test_id",
                    "name" => "",
                    "searchable" => false,
                    "orderable" => false,
                    "search" => [
                        "value" => "",
                        "regex" => false
                    ]
                ],
                [
                    "data" => "created_at",
                    "name" => "",
                    "searchable" => false,
                    "orderable" => true,
                    "search" => [
                        "value" => "",
                        "regex" => false
                    ]
                ],
                [
                    "data" => "actions",
                    "name" => "",
                    "searchable" => false,
                    "orderable" => false,
                    "search" => [
                        "value" => "",
                        "regex" => false
                    ]
                ]
            ],
            "order" => [
                [
                    "column" => 0,
                    "dir" => "asc",
                    "name" => ""
                ]
            ],
            "start" => 0,
            "length" => 10,
            "search" => [
                "value" => "",
                "regex" => false
            ],
            'searchBuilder' => [
                'criteria' => [
                    [
                        'condition' => '=',
                        'data' => 'Author',
                        'origData' => 'user_test_id',
                        'type' => 'string',
                        'value' => ['Lucas Jovencio'],
                        'value1' => 'Lucas Jovencio'
                    ]
                ],
                'logic' => 'AND'
            ],
            "format_date_locale" => "DD/MM/YYYY HH:mm",
            "timezone_locale" => "America/Sao_Paulo"
        ]);

        $dataTableQueryFactory = new DataTableQueryFactory($request);
        $config = [
            'query' => [
                "user_test_id" => function($criteria, $tableName, $matchConditional) {
                    list($queryParam, $params) = $matchConditional($criteria['condition'], 'user.name', $criteria['value'], $criteria['type']);
                    return (!empty($queryParam)) ? [" ( EXISTS ( SELECT 1 FROM user_tests user WHERE user.id = {$tableName}.user_test_id and ({$queryParam}) ) ) ", $params] : null;
                }
            ],
            'with' => ["user"],
            'select' => ["id", "title", "content", "user_test_id", "created_at"],
            'map' => fn($post) => [
                'id' => $post->id,
                'content' => $post->content,
                'title' => $post->title,
                'created_at' => $post->created_at,
                'user_test_id' => $post->user->name,
            ],
        ];

        $result = $dataTableQueryFactory->build(PostTest::class, $config);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('data', $result);
        $this->assertNotEmpty($result['data']);
        $this->assertSame($result['data'][0]['user_test_id'], "Lucas Jovencio");
    }

    public function testIWouldLikeToSearchForPostsWithoutUsingTheQueryBuilder()
    {
        $request = new Request;
        $request->merge([
            "draw" => 1,
            "columns" => [
                [
                    "data" => "id",
                    "name" => "",
                    "searchable" => false,
                    "orderable" => true,
                    "search" => [
                        "value" => "",
                        "regex" => false
                    ]
                ],
                [
                    "data" => "title",
                    "name" => "",
                    "searchable" => true,
                    "orderable" => true,
                    "search" => [
                        "value" => "",
                        "regex" => false
                    ]
                ],
                [
                    "data" => "content",
                    "name" => "",
                    "searchable" => true,
                    "orderable" => true,
                    "search" => [
                        "value" => "",
                        "regex" => false
                    ]
                ],
                [
                    "data" => "user_test_id",
                    "name" => "",
                    "searchable" => false,
                    "orderable" => false,
                    "search" => [
                        "value" => "",
                        "regex" => false
                    ]
                ],
                [
                    "data" => "created_at",
                    "name" => "",
                    "searchable" => false,
                    "orderable" => true,
                    "search" => [
                        "value" => "",
                        "regex" => false
                    ]
                ],
                [
                    "data" => "actions",
                    "name" => "",
                    "searchable" => false,
                    "orderable" => false,
                    "search" => [
                        "value" => "",
                        "regex" => false
                    ]
                ]
            ],
            "order" => [
                [
                    "column" => 0,
                    "dir" => "asc",
                    "name" => ""
                ]
            ],
            "start" => 0,
            "length" => 10,
            "search" => [
                "value" => "laravel",
                "regex" => false
            ],
            "format_date_locale" => "DD/MM/YYYY HH:mm",
            "timezone_locale" => "America/Sao_Paulo"
        ]);

        $dataTableQueryFactory = new DataTableQueryFactory($request);
        $config = [
            'query' => [
                "user_test_id" => function($criteria, $tableName, $matchConditional) {
                    list($queryParam, $params) = $matchConditional($criteria['condition'], 'user.name', $criteria['value'], $criteria['type']);
                    return (!empty($queryParam)) ? [" ( EXISTS ( SELECT 1 FROM user_tests user WHERE user.id = {$tableName}.user_test_id and ({$queryParam}) ) ) ", $params] : null;
                }
            ],
            'with' => ["user"],
            'select' => ["id", "title", "content", "user_test_id", "created_at"],
            'map' => fn($post) => [
                'id' => $post->id,
                'content' => $post->content,
                'title' => $post->title,
                'created_at' => $post->created_at,
                'user_test_id' => $post->user->name,
            ],
        ];

        $result = $dataTableQueryFactory->build(PostTest::class, $config);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('data', $result);
        $this->assertNotEmpty($result['data']);
        $this->assertTrue(strpos($result['data'][0]['title'], 'laravel') !== false);
    }

    public function testIWantAllPostFromLucasWithoutUsingTheQueryBuilder()
    {
        $request = new Request;
        $request->merge([
            "draw" => 1,
            "columns" => [
                [
                    "data" => "id",
                    "name" => "",
                    "searchable" => false,
                    "orderable" => true,
                    "search" => [
                        "value" => "",
                        "regex" => false
                    ]
                ],
                [
                    "data" => "title",
                    "name" => "",
                    "searchable" => true,
                    "orderable" => true,
                    "search" => [
                        "value" => "",
                        "regex" => false
                    ]
                ],
                [
                    "data" => "content",
                    "name" => "",
                    "searchable" => true,
                    "orderable" => true,
                    "search" => [
                        "value" => "",
                        "regex" => false
                    ]
                ],
                [
                    "data" => "user_test_id",
                    "name" => "",
                    "searchable" => true,
                    "orderable" => false,
                    "search" => [
                        "value" => "",
                        "regex" => false
                    ]
                ],
                [
                    "data" => "created_at",
                    "name" => "",
                    "searchable" => false,
                    "orderable" => true,
                    "search" => [
                        "value" => "",
                        "regex" => false
                    ]
                ],
                [
                    "data" => "actions",
                    "name" => "",
                    "searchable" => false,
                    "orderable" => false,
                    "search" => [
                        "value" => "",
                        "regex" => false
                    ]
                ]
            ],
            "order" => [
                [
                    "column" => 0,
                    "dir" => "asc",
                    "name" => ""
                ]
            ],
            "start" => 0,
            "length" => 10,
            "search" => [
                "value" => "Lucas Jovencio",
                "regex" => false
            ],
            "format_date_locale" => "DD/MM/YYYY HH:mm",
            "timezone_locale" => "America/Sao_Paulo"
        ]);

        $dataTableQueryFactory = new DataTableQueryFactory($request);
        $config = [
            'query' => [
                "user_test_id" => function($criteria, $tableName, $matchConditional) {
                    list($queryParam, $params) = $matchConditional($criteria['condition'], 'user.name', $criteria['value'], $criteria['type']);
                    return (!empty($queryParam)) ? [" ( EXISTS ( SELECT 1 FROM user_tests user WHERE user.id = {$tableName}.user_test_id and ({$queryParam}) ) ) ", $params] : null;
                }
            ],
            'with' => ["user"],
            'select' => ["id", "title", "content", "user_test_id", "created_at"],
            'map' => fn($post) => [
                'id' => $post->id,
                'content' => $post->content,
                'title' => $post->title,
                'created_at' => $post->created_at,
                'user_test_id' => $post->user->name,
            ],
        ];

        $result = $dataTableQueryFactory->build(PostTest::class, $config);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('data', $result);
        $this->assertNotEmpty($result['data']);
        $this->assertSame($result['data'][0]['user_test_id'], "Lucas Jovencio");
    }
}
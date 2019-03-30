<?php

namespace OVAC\IDoc;

use Illuminate\Console\Command;
use Illuminate\Routing\Route;
use Illuminate\Support\Collection;
use Mpociot\ApiDoc\Postman\CollectionWriter;
use Mpociot\ApiDoc\Tools\Generator;
use Mpociot\ApiDoc\Tools\RouteMatcher;
use Mpociot\Documentarian\Documentarian;
use Mpociot\Reflection\DocBlock;
use ReflectionClass;
use ReflectionException;

/**
 * This custom generator will parse and generate a beautiful
 * interractive documentation with openAPI schema.
 */
class IDocGeneratorCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'idoc:generate
                            {--force : Force rewriting of existing routes}
    ';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate interractive api documentation.';

    private $routeMatcher;

    public function __construct(RouteMatcher $routeMatcher)
    {
        parent::__construct();
        $this->routeMatcher = $routeMatcher;
    }

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        $usingDingoRouter = strtolower(config('idoc.router')) == 'dingo';
        if ($usingDingoRouter) {
            $routes = $this->routeMatcher->getDingoRoutesToBeDocumented(config('idoc.routes'));
        } else {
            $routes = $this->routeMatcher->getLaravelRoutesToBeDocumented(config('idoc.routes'));
        }

        $generator = new IDocGenerator();

        $parsedRoutes = $this->processRoutes($generator, $routes);

        $parsedRoutes = collect($parsedRoutes)->groupBy('group');

        $this->writeMarkdown($parsedRoutes);
    }

    /**
     * @param  Collection $parsedRoutes
     *
     * @return void
     */
    private function writeMarkdown($parsedRoutes)
    {
        $outputPath = config('idoc.output');
        $infoFile = $outputPath . DIRECTORY_SEPARATOR . 'source' . DIRECTORY_SEPARATOR . 'info.md';
        $targetFile = $outputPath . DIRECTORY_SEPARATOR . 'source' . DIRECTORY_SEPARATOR . 'index.md';
        $compareFile = $outputPath . DIRECTORY_SEPARATOR . 'source' . DIRECTORY_SEPARATOR . '.compare.md';
        $prependFile = $outputPath . DIRECTORY_SEPARATOR . 'source' . DIRECTORY_SEPARATOR . 'prepend.md';
        $appendFile = $outputPath . DIRECTORY_SEPARATOR . 'source' . DIRECTORY_SEPARATOR . 'append.md';

        $infoText = view('idoc::partials.info')
            ->with('outputPath', ltrim($outputPath, 'public/'))
            ->with('showPostmanCollectionButton', config('idoc.collections'));

        // dd($parsedRoutes);
        $parsedRouteOutput = $parsedRoutes->map(function ($routeGroup) {
            return $routeGroup->map(function ($route) {
                // dd($route);
                $route['output'] = (string) view('idoc::partials.route')->with('route', $route)->render();

                return $route;
            });
        });

        $frontmatter = view('idoc::partials.frontmatter');
        /*
         * In case the target file already exists, we should check if the documentation was modified
         * and skip the modified parts of the routes.
         */
        if (file_exists($targetFile) && file_exists($compareFile)) {
            $generatedDocumentation = file_get_contents($targetFile);
            $compareDocumentation = file_get_contents($compareFile);

            if (preg_match('/---(.*)---\\s<!-- START_INFO -->/is', $generatedDocumentation, $generatedFrontmatter)) {
                $frontmatter = trim($generatedFrontmatter[1], "\n");
            }

            $parsedRouteOutput->transform(function ($routeGroup) use ($generatedDocumentation, $compareDocumentation) {
                return $routeGroup->transform(function ($route) use ($generatedDocumentation, $compareDocumentation) {
                    if (preg_match('/<!-- START_' . $route['id'] . ' -->(.*)<!-- END_' . $route['id'] . ' -->/is', $generatedDocumentation, $existingRouteDoc)) {
                        $routeDocumentationChanged = (preg_match('/<!-- START_' . $route['id'] . ' -->(.*)<!-- END_' . $route['id'] . ' -->/is', $compareDocumentation, $lastDocWeGeneratedForThisRoute) && $lastDocWeGeneratedForThisRoute[1] !== $existingRouteDoc[1]);
                        if ($routeDocumentationChanged === false || $this->option('force')) {
                            if ($routeDocumentationChanged) {
                                $this->warn('Discarded manual changes for route [' . implode(',', $route['methods']) . '] ' . $route['uri']);
                            }
                        } else {
                            $this->warn('Skipping modified route [' . implode(',', $route['methods']) . '] ' . $route['uri']);
                            $route['modified_output'] = $existingRouteDoc[0];
                        }
                    }

                    return $route;
                });
            });
        }

        $prependFileContents = file_exists($prependFile)
        ? file_get_contents($prependFile) . "\n" : '';
        $appendFileContents = file_exists($appendFile)
        ? "\n" . file_get_contents($appendFile) : '';

        $documentarian = new Documentarian();

        $markdown = view('idoc::documentarian')
            ->with('writeCompareFile', false)
            ->with('frontmatter', $frontmatter)
            ->with('infoText', $infoText)
            ->with('prependMd', $prependFileContents)
            ->with('appendMd', $appendFileContents)
            ->with('outputPath', config('idoc.output'))
            ->with('showPostmanCollectionButton', config('idoc.collections'))
            ->with('parsedRoutes', $parsedRouteOutput);

        if (!is_dir($outputPath)) {
            $documentarian->create($outputPath);
        }

        // Write output file
        file_put_contents($targetFile, $markdown);

        // Write comparable markdown file
        $compareMarkdown = view('idoc::documentarian')
            ->with('writeCompareFile', true)
            ->with('frontmatter', $frontmatter)
            ->with('infoText', $infoText)
            ->with('prependMd', $prependFileContents)
            ->with('appendMd', $appendFileContents)
            ->with('outputPath', config('idoc.output'))
            ->with('showPostmanCollectionButton', config('idoc.collections'))
            ->with('parsedRoutes', $parsedRouteOutput);

        file_put_contents($compareFile, $compareMarkdown);

        $this->info('Wrote index.md to: ' . $outputPath);

        $this->info('Generating API HTML code');

        $documentarian->generate($outputPath);

        $this->info('Wrote HTML documentation to: ' . $outputPath . '/index.html');

        file_put_contents($infoFile, $infoText);

        if (config('idoc.collections')) {
            $collectionRoutes = $parsedRoutes;

            $this->info('Generating Postman collection');
            file_put_contents($outputPath . DIRECTORY_SEPARATOR . 'collection.json', $this->generatePostmanCollection($collectionRoutes));

            $this->info('Generating OPEN API 3.0.0 Config');
            file_put_contents($outputPath . DIRECTORY_SEPARATOR . 'openapi.json', $this->generateOpenApi3Config($parsedRoutes));

            file_put_contents($outputPath . '/interractive.html', view('idoc::interractive')->with('title', config('idoc.title')));
            $this->info('Wrote an interractive HTML documentation to: ' . $outputPath . '/interractive.html');
        }

        if ($logo = config('idoc.logo')) {
            copy(
                $logo,
                $outputPath . DIRECTORY_SEPARATOR . 'images' . DIRECTORY_SEPARATOR . 'logo.png'
            );
        }
    }

    /**
     * @param Generator $generator
     * @param array $routes
     *
     * @return array
     */
    private function processRoutes(IDocGenerator $generator, array $routes)
    {
        $parsedRoutes = [];
        foreach ($routes as $routeItem) {
            $route = $routeItem['route'];
            /** @var Route $route */
            if ($this->isValidRoute($route) && $this->isRouteVisibleForDocumentation($route->getAction()['uses'])) {
                $parsedRoutes[] = $generator->processRoute($route, $routeItem['apply']);
                $this->info('Processed route: [' . implode(',', $generator->getMethods($route)) . '] ' . $generator->getUri($route));
            } else {
                $this->warn('Skipping route: [' . implode(',', $generator->getMethods($route)) . '] ' . $generator->getUri($route));
            }
        }

        return $parsedRoutes;
    }

    /**
     * @param $route
     *
     * @return bool
     */
    private function isValidRoute(Route $route)
    {
        return !is_callable($route->getAction()['uses']) && !is_null($route->getAction()['uses']);
    }

    /**
     * @param $route
     *
     * @throws ReflectionException
     *
     * @return bool
     */
    private function isRouteVisibleForDocumentation($route)
    {
        list($class, $method) = explode('@', $route);
        $reflection = new ReflectionClass($class);

        if (!$reflection->hasMethod($method)) {
            return false;
        }

        $comment = $reflection->getMethod($method)->getDocComment();

        if ($comment) {
            $phpdoc = new DocBlock($comment);

            return collect($phpdoc->getTags())
                ->filter(function ($tag) use ($route) {
                    return $tag->getName() === 'hideFromAPIDocumentation';
                })
                ->isEmpty();
        }

        return true;
    }

    /**
     * Generate Postman collection JSON file.
     *
     * @param Collection $routes
     *
     * @return string
     */
    private function generatePostmanCollection(Collection $routes)
    {
        $writer = new CollectionWriter($routes);

        return $writer->getCollection();
    }

    /**
     * Generate Open API 3.0.0 collection json file.
     *
     * @param Collection $routes
     *
     * @return string
     */
    private function generateOpenApi3Config(Collection $routes)
    {
        $result = $routes->map(function ($routeGroup, $groupName) use ($routes) {

            return collect($routeGroup)->map(function ($route) use ($groupName, $routes, $routeGroup) {

                $methodGroup = $routeGroup->where('uri', $route['uri'])->mapWithKeys(function ($route) use ($groupName, $routes) {

                    $bodyParameters = collect($route['bodyParameters'])->map(function ($schema, $name) use ($routes) {

                        $type = $schema['type'];
                        $default = $schema['value'];

                        if ($type === 'float') {
                            $type = 'number';
                        }

                        if ($type === 'json' && $default) {
                            $type = 'object';
                            $default = json_decode($default);
                        }

                        return [
                            'in' => 'formData',
                            'name' => $name,
                            'description' => $schema['description'],
                            'required' => $schema['required'],
                            'type' => $type,
                            'default' => $default,
                        ];
                    });

                    $jsonParameters = [
                        'application/json' => [
                            'schema' => [
                                'type' => 'object',
                            ]
                             + (
                                count($required = $bodyParameters
                                        ->values()
                                        ->where('required', true)
                                        ->pluck('name'))
                                ? ['required' => $required]
                                : []
                            )

                             + (
                                count($properties = $bodyParameters
                                        ->values()
                                        ->filter()
                                        ->mapWithKeys(function ($parameter) use ($routes) {
                                            return [
                                                $parameter['name'] => [
                                                    'type' => $parameter['type'],
                                                    'example' => $parameter['default'],
                                                    'description' => $parameter['description'],
                                                ],
                                            ];
                                        }))
                                ? ['properties' => $properties]
                                : []
                            )

                             + (
                                count($properties = $bodyParameters
                                        ->values()
                                        ->filter()
                                        ->mapWithKeys(
                                            function ($parameter) {
                                                return [$parameter['name'] => $parameter['default']];
                                            }
                                        ))
                                ? ['example' => $properties]
                                : []
                            )
                        ],
                    ];

                    $queryParameters = collect($route['queryParameters'])->map(function ($schema, $name) {
                        return [
                            'in' => 'query',
                            'name' => $name,
                            'description' => $schema['description'],
                            'required' => $schema['required'],
                            'schema' => [
                                'type' => $schema['type'],
                                'example' => $schema['value'],
                            ],
                        ];
                    });

                    $pathParameters = collect($route['pathParameters'] ?? [])->map(function ($schema, $name) use ($route) {
                        return [
                            'in' => 'path',
                            'name' => $name,
                            'description' => $schema['description'],
                            'required' => $schema['required'],
                            'schema' => [
                                'type' => $schema['type'],
                                'example' => $schema['value'],
                            ],
                        ];
                    });

                    $headerParameters = collect($route['headers'])->map(function ($value, $header) use ($route) {

                        if ($header === 'Authorization') {
                            return;
                        }

                        return [
                            'in' => 'header',
                            'name' => $header,
                            'description' => '',
                            'required' => true,
                            'schema' => [
                                'type' => 'string',
                                'default' => $value,
                                'example' => $value,
                            ],
                        ];
                    });

                    return [
                        strtolower($route['methods'][0]) => (

                            (
                                $route['authenticated']
                                ? ['security' => [
                                    ['BearerAuth' => []],
                                ]]
                                : []
                            )

                             + ([
                                "tags" => [
                                    $groupName,
                                ],
                                'operationId' => $route['title'],
                                'description' => $route['description'],
                             ]) +

                            (
                                count(array_intersect(['POST', 'PUT', 'PATCH'], $route['methods']))
                                ? ['requestBody' => [
                                    'description' => $route['description'],
                                    'required' => true,
                                    'content' => collect($jsonParameters)->filter()->toArray(),
                                ]]
                                : []
                            ) +

                            [
                                'parameters' => (

                                    array_merge(
                                        collect($queryParameters->values()->toArray())
                                            ->filter()
                                            ->toArray(),
                                        collect($pathParameters->values()->toArray())
                                            ->filter()
                                            ->toArray()
                                    ) +

                                    collect($headerParameters->values()->toArray())
                                        ->filter()
                                        ->values()
                                        ->toArray()
                                ),

                                'responses' => [
                                    200 => [
                                        'description' => 'success',
                                    ] +
                                    (
                                        count($route['response'] ?? [])
                                        ? ['content' => [
                                            'application/json' => [
                                                'schema' => [
                                                    'type' => 'object',
                                                    'example' => json_decode($route['response'][0]['content'], true),
                                                ],
                                            ],
                                        ]]
                                        : []
                                    ),
                                ],

                                'x-code-samples' => collect(config('idoc.language-tabs'))->map(function ($name, $lang) use ($route) {
                                    return [
                                        'lang' => $name,
                                        'source' => view('idoc::partials.routes.' . $lang, compact('route'))->render(),
                                    ];
                                })->values()->toArray(),
                            ]
                        ),
                    ];
                });

                return collect([
                    ('/' . $route['uri']) => $methodGroup,
                ]);
            });
        });

        $paths = [];

        foreach ($result->filter()->toArray() as $groupName => $group) {
            foreach ($group as $key => $value) {
                $paths[key($value)] = $value[key($value)];
            }
        }

        $collection = [

            'openapi' => '3.0.0',

            'info' => [
                'title' => config('idoc.title'),
                'version' => 'v5.0',
                'description' => config('idoc.description'),
                "x-logo" => [
                    "url" => "/docs/images/logo.png",
                    "altText" => config('idoc.title'),
                    "backgroundColor" => '',
                ],
            ],

            'components' => [
                'securitySchemes' => [
                    'BearerAuth' => [
                        'type' => 'http',
                        'scheme' => 'bearer',
                        'bearerFormat' => 'JWT',
                    ],
                ],

                'schemas' => $routes->mapWithKeys(function ($routeGroup, $groupName) {

                    if ($groupName != 'Payment processors') {
                        return [];
                    }

                    return collect($routeGroup)->mapWithKeys(function ($route) use ($groupName, $routeGroup) {

                        $bodyParameters = collect($route['bodyParameters'])->map(function ($schema, $name) {

                            $type = $schema['type'];

                            if ($type === 'float') {
                                $type = 'number';
                            }

                            if ($type === 'json') {
                                $type = 'object';
                            }

                            return [
                                'in' => 'formData',
                                'name' => $name,
                                'description' => $schema['description'],
                                'required' => $schema['required'],
                                'type' => $type,
                                'default' => $schema['value'],
                            ];
                        });

                        return ["PM{$route['paymentMethod']->id}" => ['type' => 'object']

                             + (
                                count($required = $bodyParameters
                                        ->values()
                                        ->where('required', true)
                                        ->pluck('name'))
                                ? ['required' => $required]
                                : []
                            )

                             + (
                                count($properties = $bodyParameters
                                        ->values()
                                        ->filter()
                                        ->mapWithKeys(function ($parameter) {
                                            return [
                                                $parameter['name'] => [
                                                    'type' => $parameter['type'],
                                                    'example' => $parameter['default'],
                                                    'description' => $parameter['description'],
                                                ],
                                            ];
                                        }))
                                ? ['properties' => $properties]
                                : []
                            )

                             + (
                                count($properties = $bodyParameters
                                        ->values()
                                        ->filter()
                                        ->mapWithKeys(function ($parameter) {
                                            return [$parameter['name'] => $parameter['default']];
                                        }))
                                ? ['example' => $properties]
                                : []
                            )
                        ];
                    });
                })->filter(),
            ],

            'servers' => [
                [
                    'url' => config('app.url'),
                    'description' => 'Documentation generator server.',
                ],
            ],

            'paths' => $paths,
        ];

        return json_encode($collection);
    }
}

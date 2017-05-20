<?php
//ini_set('error_reporting', E_ALL);
//ini_set('display_errors', E_ALL);
require_once dirname(dirname(__FILE__)) . '/vendor/autoload.php';

use Symfony\Component\Translation\Loader\YamlFileLoader;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Debug\ErrorHandler;


$app = new Silex\Application();
$app['debug'] = true;
$app->register(new Silex\Provider\FormServiceProvider());
$app->register(new Silex\Provider\ValidatorServiceProvider());
$app->register(new Silex\Provider\UrlGeneratorServiceProvider());
$app->register(new Silex\Provider\SessionServiceProvider());

$app->register(new Silex\Provider\TwigServiceProvider(), array(
    'twig.path' => dirname(dirname(__FILE__)) . '/src/views',
));
ErrorHandler::register();

$app->register(
    new Silex\Provider\DoctrineServiceProvider(),
    array(
        'db.options' => array(
            'driver'    => 'pdo_mysql',
            'host'      => 'localhost',
            'dbname'    => '',
            'user'      => '',
            'password'  => '',
            'charset'   => 'utf8',
        ),
    )
);

$app->register(
    new Silex\Provider\TranslationServiceProvider(),
    array(
        'locale' => 'pl',
        'locale_fallbacks' => array('pl'),
    )
);

$app->register(
    new Silex\Provider\SecurityServiceProvider(),
    array(
        'security.firewalls' => array(
            'admin' => array(
                'pattern' => '^.*$',
                'form' => array(
                    'login_path' => 'auth_login',
                    'check_path' => 'auth_login_check',
                    'default_target_path'=> '/index',
                    'username_parameter' => 'loginForm[login]',
                    'password_parameter' => 'loginForm[password]',
                ),
                'anonymous' => true,
                'logout' => array(
                    'logout_path' => 'auth_logout',
                    'target_url' => '/index'
            ),
                'users' => $app->share(
                    function() use ($app)
                    {
                        return new Provider\UserProvider($app);
                    }
                ),
            ),
        ),
        'security.access_rules' => array(
            array('^/secret/safe$', 'ROLE_ADMIN'),
            array('^/account$', 'ROLE_USER'),
            array('^.+$', 'IS_AUTHENTICATED_ANONYMOUSLY')
        ),
        'security.role_hierarchy' => array(
            'ROLE_ADMIN' => array('ROLE_USER'),
            'ROLE_USER' => array('IS_AUTHENTICATED_ANONYMOUSLY'),
        ),
    )
);

$app->register(new Silex\Provider\SessionServiceProvider());

$app['translator'] = $app->share($app->extend('translator', function ($translator, $app) {
    $translator->addLoader('yaml', new YamlFileLoader());
    $translator->addResource('yaml', dirname(dirname(__FILE__)) . '/config/locales/pl.yml', 'pl');
    return $translator;
}));

$app->error(
    function (
        \Exception $e, $code
    ) use ($app) {

        if ($e instanceof Symfony\Component\HttpKernel\Exception\NotFoundHttpException) {
            $code = (string)$e->getStatusCode();
        }

        if ($app['debug']) {
            return;
        }

        // 404.html, or 40x.html, or 4xx.html, or error.html
        $templates = array(
            'errors/'.$code.'.twig',
            'errors/'.substr($code, 0, 2).'x.twig',
            'errors/'.substr($code, 0, 1).'xx.twig',
            'errors/default.twig',
        );

        return new Response(
            $app['twig']->resolveTemplate($templates)->render(
                array('code' => $code)
            ),
            $code
        );

    }
);

$app->mount('/', new Controller\IndexController());
$app->mount('/auth', new Controller\AuthController());
$app->mount('/account', new Controller\AccountController());
$app->mount('/url', new Controller\UrlController());
$app->mount('/secret/safe', new Controller\AdminController());

$app->run();

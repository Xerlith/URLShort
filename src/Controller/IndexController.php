<?php
/**
 * Index controller.
 *
 * @link http://epi.uj.edu.pl
 * @author epi(at)uj(dot)edu(dot)pl
 * @copyright EPI 2015
 */

namespace Controller;

use Silex\Application;
use Silex\ControllerProviderInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Class IndexController.
 *
 * @package Controller
 * @implements ControllerProviderInterface
 */
class IndexController implements ControllerProviderInterface
{

    /**
     * Data for view.
     *
     * @access protected
     * @var array $view
     */
    protected $view = array();

    /**
     * Routing settings.
     *
     * @access public
     *
     * @param Silex\Application $app Silex application
     *
     * @return IndexController Result
     */
    public function connect(Application $app)
    {
        $indexController = $app['controllers_factory'];
        $indexController->get('/index', array($this, 'indexAction'));
        $indexController->get('/index/', array($this, 'indexAction'));
        //$indexController->match('/shorten', array($this, 'shortenUrl'))->bind('shorten');
        $indexController->get('/', array($this, 'indexAction'))->bind('index');
        return $indexController;
    }

    /**
     * Index action.
     *
     * @access public
     *
     * @param Silex\Application $app Silex application
     * @param Symfony\Component\HttpFoundation\Request $request Request object
     *
     * @return string Output
     */
    public function indexAction(Application $app)
    {
        return $app['twig']->render('index/index.twig', $this->view);
    }
}

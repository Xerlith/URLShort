<?php
/**
 * Account controller.
 *
 * @author EPI <epi@uj.edu.pl>
 * @link http://epi.uj.edu.pl
 * @copyright 2015 EPI
 */

namespace Controller;

use Model\UrlsModel;
use Model\UsersModel;
use Silex\Application;
use Silex\ControllerProviderInterface;
use Symfony\Component\HttpFoundation\Request;


/**
 * Class AccountController.
 *
 * @package Controller
 * @implements ControllerProviderInterface
 */
class AccountController implements ControllerProviderInterface
{
    /**
     * Data for view.
     *
     * @access protected
     *
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
     * @return AccountController Result
     */
    public function connect(Application $app)
    {
        $accountController = $app['controllers_factory'];
        $accountController->get('/{page}', array($this, 'userAction'))
                        ->value('page', 1)->bind('user_panel');
        return $accountController;
    }

    /**
     * user action.
     *
     * @access public
     *
     * @param Silex\Application $app Silex application
     * @param Symfony\Component\HttpFoundation\Request $request Request object
     *
     * @return string Output
     */
    public function userAction(Application $app, Request $request)
    {
        if ($app['security']->isGranted('IS_AUTHENTICATED_FULLY')) {
            try {
                $userModel = new UsersModel($app);
                $username = $app['security']->getToken()->getUser()->getUsername();
                $user_id = $userModel->getUserByLogin($username);

                $pageLimit = 20;
                $page = (int)$request->get('page', 1);
                $urlModel = new UrlsModel($app);

                $this->view = array_merge(
                    $this->view, $urlModel->getPaginatedUrls($page, $pageLimit, $user_id['id'])
                );
            } catch (\PDOException $e) {
                $app->abort(404, $app['translator']->trans('URLs not found'));
            }
            return $app['twig']->render('user/view.twig', $this->view);
        } else {
            return $app->redirect(
                $app['url_generator']->generate(
                    'auth_login', 301
                )
            );
        }
    }
}
<?php
/**
 * Administration panel controller.
 *
 * @author EPI <epi@uj.edu.pl>
 * @link http://epi.uj.edu.pl
 * @copyright 2015 EPI
 */

namespace Controller;

use Model\UrlsModel;
use Silex\Application;
use Silex\ControllerProviderInterface;
use Symfony\Component\HttpFoundation\Request;
use Form\UrlForm;
use Form\LoginForm;
use Model\UsersModel;

/**
 * Class AdminController.
 *
 * @package Controller
 * @implements ControllerProviderInterface
 */
class AdminController implements ControllerProviderInterface
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
     * @return AuthController Result
     */
    public function connect(Application $app)
    {
        $adminController = $app['controllers_factory'];
        $adminController->match('login', array($this, 'loginAdmin'))
            ->bind('admin_login');
        $adminController->get('logout', array($this, 'logoutAdmin'))
            ->bind('admin_logout');
        $adminController->get('/', array($this, 'adminPanel'))
            ->bind('admin_panel');
        $adminController->get('', array($this, 'adminPanel'));
        $adminController->get('/drop_user/{id}', array($this, 'deleteUserAction'))
            ->bind('delete_user');
        $adminController->get('/drop_user/{id}/', array($this, 'deleteUserAction'));
        $adminController->get('/users/{page}', array($this, 'viewUsers'))
            ->value('page', 1)->bind('admin_user');
        $adminController->get('/users/{page}/', array($this, 'viewUsers'));
        $adminController->get('/users/', array($this, 'viewUsers'))
            ->value('page', 1);
        $adminController->get('/urls/{page}', array($this, 'viewUrls'))
            ->value('page', 1)->bind('admin_urls');
        $adminController->get('/urls/{page}/', array($this, 'viewUrls'));
        $adminController->match('/delete/{id}', array($this, 'deleteUrlAction'));
        $adminController->match('/delete/{id}/', array($this, 'deleteUrlAction'))
            ->bind('admin_delete');
        $adminController->match('/userpwd/{id}', array($this, 'changePasswordAction'));
        $adminController->match('/userpwd/{id}/', array($this, 'changePasswordAction'))
            ->bind('user_password');
        $adminController->match('/popularity/{id}/{page}',array($this, 'popularityAction'));
        $adminController->match('/popularity/{id}/{page}/', array($this, 'popularityAction'))
            ->value('page', 1)->bind('admin_popularity');
        $adminController->match('/popularity/{id}/', array($this, 'popularityAction'))
            ->value('page',1);
        return $adminController;
    }

    /**
     * @param Application $app
     * @return mixed
     */
    public function adminPanel(Application $app)
    {
        return $app['twig']->render('admin/panel.twig', $this->view);

    }

    /**
     * @param Application $app
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function popularityAction(Application $app, Request $request){
        if ($app['security']->isGranted('ROLE_ADMIN')) {

            $pageLimit = 10;
            $page = (int)$request->get('page', 1);
            $id = (int) $request->get('id', null);
            $urlsModel = new UrlsModel($app);

            $this->view = array_merge(
                $this->view, $urlsModel->getPaginatedVisits($page, $pageLimit, $id)
            );
            return $app['twig']->render('admin/popularity.twig', $this->view);
        } else {
            return $app->redirect(
                $app['url_generator']->generate(
                    'index', 301
                )
            );
        }
    }


    /**
     * @param Application $app
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     * @throws \Doctrine\DBAL\DBALException
     */
    public function deleteUrlAction(Application $app, Request $request){
        if (!$app['security']->isGranted('ROLE_ADMIN')) {
            $app['session']->getFlashBag()->add(
                'message', array(
                    'type' => 'danger',
                    'content' =>
                        $app['translator']->trans("You don't have the rights to do that!")
                )
            );
            return $app->redirect(
                $app['url_generator']->generate(
                    'index', array(), 301)
            );
        }
        $urlModel = new UrlsModel($app);
        $id = (int)$request->get('id', null);
        $url_data = $urlModel->getOneById($id);
        if (!isset($url_data['user_id'])) {
            $app['session']->getFlashBag()->add(
                'message', array(
                    'type' => 'danger',
                    'content' =>
                        $app['translator']->trans('URL could not be found.')
                )
            );
            return $app['twig']->render('admin/urls.twig', $this->view);
        }
        $form = $app['form.factory']
            ->createBuilder(new UrlForm(), $url_data)->getForm();
        $form->remove('url');
        $form->remove('user_id');
        $form->remove('short_url');
        $form->handleRequest($request);
        if ($form->isValid()) {
            try {
                $urlModel->deleteUrl($id);
            } catch (\PDOException $e) {
                $app['session']->getFlashBag()->add(
                    'message', array(
                        'type' => 'danger',
                        'content' =>
                            $app['translator']->trans('URL could not be deleted.')
                    )
                );
            }
            $app['session']->getFlashBag()->add(
                'message', array(
                    'type' => 'success',
                    'content' =>
                        $app['translator']->trans('URL deleted.')
                )
            );
            return $app->redirect(
                $app['url_generator']->generate(
                    'index', array(), 301)
            );
        }
        $this->view = array(
            'form' => $form->createView(),
            'error' => $app['security.last_error'],
            'short' => $url_data['short_url'],
            'id' => $url_data['url_id']
        );
        return $app['twig']->render('admin/deleteurl.twig', $this->view);
    }

    /**
     * @param Application $app
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function viewUsers(Application $app, Request $request)
    {
        if ($app['security']->isGranted('ROLE_ADMIN')) {

            $pageLimit = 10;
            $page = (int)$request->get('page', 1);
            $userModel = new UsersModel($app);

            $this->view = array_merge(
                $this->view, $userModel->getPaginatedUsers($page, $pageLimit)
            );
            return $app['twig']->render('admin/users.twig', $this->view);
        } else {
            return $app->redirect(
                $app['url_generator']->generate(
                    'index', 301
                )
            );
        }
    }

    /**
     * @param Application $app
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function viewUrls(Application $app, Request $request)
    {
        if ($app['security']->isGranted('ROLE_ADMIN')) {

            $pageLimit = 10;
            $page = (int)$request->get('page', 1);
            $urlModel = new UrlsModel($app);

            $this->view = array_merge(
                $this->view, $urlModel->getPaginatedUrlsAdmin($page, $pageLimit)
            );
            return $app['twig']->render('admin/urls.twig', $this->view);
        } else {
            return $app->redirect(
                $app['url_generator']->generate(
                    'index', 301
                )
            );
        }
    }

    /**
     * @param Application $app
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     * @throws \Doctrine\DBAL\DBALException
     */
    public function changePasswordAction(Application $app, Request $request){
        if ($app['security']->isGranted('ROLE_ADMIN')) {
            $id = (int)$request->get('id', null);
            $usersModel = new UsersModel($app);
            $exists = $usersModel->getUserById($id);
            if (!empty($exists)) {
                $data = array(
                    'user_id' => $id,
                    'password' => '',
                    'confirm' => ''
                );

                $form = $app['form.factory']
                    ->createBuilder(new LoginForm(), $data)
                    ->getForm();

                $form->remove('login');
                $form->add('user_id', 'hidden');
                $form->add('confirm', 'password');

                $form->handleRequest($request);
                if($form->isValid()) {
                    $data = $form->getData();
                    if ($data['password'] === $data['confirm']) {
                        unset($data['confirm']);
                        $data['password'] = $app['security.encoder.digest']->encodePassword($data['password'], '');
                        try {
                            $usersModel->changePassword($data);

                            $app->redirect(
                                'admin_user', 301
                            );
                            $app['session']->getFlashBag()->add(
                                'message', array(
                                    'type' => 'success',
                                    'content' =>
                                        $app['translator']->trans('Password changed succesfully.')
                                )
                            );
                        } catch (\PDOException $e) {
                            $app['session']->getFlashBag()->add(
                                'message', array(
                                    'type' => 'danger',
                                    'content' =>
                                        $app['translator']->trans('Password could not be changed.')
                                )
                            );
                        }
                        $app->redirect(
                            'admin_user', 301
                        );
                    } else {
                        $app['session']->getFlashBag()->add(
                            'message', array(
                                'type' => 'danger',
                                'content' =>
                                    $app['translator']->trans('Fields do not match!')
                            )
                        );
                    }
                }

            } else {
                $app['session']->getFlashBag()->add(
                    'message', array(
                        'type' => 'danger',
                        'content' =>
                            $app['translator']->trans('User does not exist.')
                    )
                );
                return $app->redirect(
                    $app['url_generator']->generate(
                        'admin_user', 301
                    )
                );
            }

        } else {
            return $app->redirect(
                $app['url_generator']->generate(
                    'admin_user', 301
                )
            );
        }

        $this->view = array(
            'form' => $form->createView(),
            'error' => $app['security.last_error']($request),
            'id' => $id
        );
        return $app['twig']->render('admin/password.twig', $this->view);
    }

    /**
     * @param Application $app
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function deleteUserAction(Application $app, Request $request){
        if ($app['security']->isGranted('ROLE_ADMIN')) {
            $id = (int)$request->get('id', null);
            $usersModel = new UsersModel($app);
            $exists = $usersModel->getUserById($id);

            if (!empty($exists)
                && $exists['role_id'] != '1') {
                $urlsModel = new UrlsModel($app);
                $urls = $urlsModel->getAllByUser($id);

                foreach($urls as $url_id){
                    $url_id = $url_id['url_id'];
                    $urlsModel->deleteUrl($url_id);
                }
                unset($urls);
                unset($url_id);
                $usersModel->deleteUser($id);

                $app['session']->getFlashBag()->add(
                    'message', array(
                        'type' => 'success',
                        'content' =>
                            $app['translator']->trans('User deleted')
                    )
                );
                return $app->redirect(
                    $app['url_generator']->generate(
                        'admin_user', array(), 301)
                );
            }
            else {
                $app['session']->getFlashBag()->add(
                    'message', array(
                        'type' => 'danger',
                        'content' =>
                            $app['translator']->trans('User does not exist or cannot be deleted!')
                    )
                );
                return $app->redirect(
                    $app['url_generator']->generate(
                        'admin_user', array(), 301)
                );
            }
        } else {
            return $app->redirect(
                $app['url_generator']->generate(
                    'admin_login', 301
                )
            );
        }
    }
}
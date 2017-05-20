<?php
/**
 * Url controller.
 *
 * @link http://epi.uj.edu.pl
 * @author epi(at)uj(dot)edu(dot)pl
 * @copyright EPI 2015
 */

namespace Controller;

use Silex\Application;
use Silex\ControllerProviderInterface;
use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\HttpFoundation\Request;
use Model\UrlsModel;
use Model\UsersModel;
use Form\UrlForm;
use Symfony\Component\HttpFoundation\Session;

/**
 * Class UrlController.
 *
 * @package Controller
 * @implements ControllerProviderInterface
 */
class UrlController implements ControllerProviderInterface
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
     * @return UrlController Result
     */
    public function connect(Application $app)
    {
        $urlController = $app['controllers_factory'];
        $urlController->get('/', array($this, 'shortenUrl'));
        $urlController->match('/', array($this, 'shortenUrl'))
            ->bind('shorten');
        $urlController->get('/created/{short}', array($this, 'displayFresh'));
        $urlController->match('/created/{short}', array($this, 'displayFresh'))
            ->bind('fresh');
        $urlController->get('/{short}', array($this, 'redirectToUrl'));
        $urlController->match('/{short}', array($this, 'redirectToUrl'))
            ->bind('redirect');
        $urlController->match('/delete/{id}', array($this, 'deleteAction'));
        $urlController->match('/delete/{id}/', array($this, 'deleteAction'))
            ->bind('delete');
        return $urlController;
    }

    /**
     * Shortens URL.
     * @param Application $app
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function shortenUrl(Application $app, Request $request)
    {
        try {
            unset($short);
            $urlsModel = new UrlsModel($app);
            do {
                $short = substr(str_shuffle('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 5);
                $urlExists = $urlsModel->uniqueUrl($short);
            } while (!empty($urlExists));


            $data = $this->createFormValues($app, $short);
            unset($short);

            $form = $app['form.factory']
                ->createBuilder(new UrlForm(), $data)
                ->getForm();

            $form->handleRequest($request);

            if ($form->isValid()) {
                $data = $form->getData();
                $validData = $this->validateUrl($data);
                $urlsModel->insertShort($validData);
                $app['session']->getFlashBag()->add(
                    'message',
                    array(
                        'type' => 'success',
                        'content' =>
                            $app['translator']->trans('URL added.')
                    )
                );
                return $app->redirect(
                    $app['url_generator']->generate(
                        'fresh',
                        array('short' => $validData['short_url'])
                    ),
                    301
                );

            }

            $this->view = array(
                'form' => $form->createView(),
                'error' => $app['security.last_error']
            );
            return $app['twig']->render('url/shorten.twig', $this->view);
        } catch(Exception $e){
            return $app->abort(404, $app['translator']->trans('Shortener service failed'));
        }
    }

    /**
     * Displays the freshly made short URl.
     * @param Application $app
     * @param Request $request
     * @return mixed
     */
    public function displayFresh(Application $app, Request $request)
    {
        $view = array();
        $view['short'] = (string)$request->get('short', '');
        return $app['twig']->render('url/created.twig', $view);

    }


    /**
     * Redirects to URl with view counting.
     * @param Application $app
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function redirectToUrl(Application $app, Request $request)
    {
        try {
            $urlModel = new UrlsModel($app);
            $short = (string)$request->get('short');
            $url = $urlModel->uniqueUrl($short);
            $url = $url['0'];
            $ip = $request->getClientIp();

            $data = array(
                'id' => '',
                'visitor_ip' => $ip,
                'url_id' => $url,
                'visit_date' => null
            );
            $popModel = new UrlsModel($app);
            $popModel->registerVisit($data);
            $longUrl = $urlModel->getLongByShort($short);
            return $app->redirect($longUrl['url']);
        } catch (Exception $e) {
            $app->abort(404, $app['translator']->trans('Could not redirect'));
        }
    }


    /**
     * Creates default values for the url creation form.
     * @param Application $app
     * @param $short
     * @return array
     */
    public function createFormValues(Application $app, $short)
    {

        // creating values for anonymous and non-anonymous URL creation:
        if ($app['security']->isGranted('IS_AUTHENTICATED_FULLY')) {
            $user_id = $this->currentUserId($app);


            $data = array(
                'url_id' => '',
                'url' => '',
                'user_id' => $user_id,
                'short_url' => $short
            );
        } else {
            $data = array(
                'url_id' => '',
                'url' => '',
                'user_id' => '1',
                'short_url' => $short
            );
        }
        return $data;
    }

    /**
     * Checks if the URL is a valid one.
     * @param $data
     * @return mixed
     */
    public function validateUrl($data)
    {
        $url = $data['url'];
        $pattern = '/^(http|ftp|https)\:\/\/[0-9a-zA-Z]+.*$/';

        if (preg_match($pattern, $url)) {
            return $data;
        } else {
            $data['url'] = 'http://' . $data['url'];
            return $data;
        }
    }

    /**
     * Gets current logged in user's ID.
     * @param Application $app
     * @return int $user_id
     */
    public function currentUserId(Application $app)
    {
        try {
            $userModel = new UsersModel($app);
            $username = $app['security']->getToken()->getUser()->getUsername();
            $user_data = $userModel->getUserByLogin($username);
            return $user_id = $user_data['id'];
        } catch (Exception $e){
            $app->abort(404, $app['translator']->trans('Could not find user ID'));
        }
    }

    /**
     * Deletes the URl.
     * @param Application $app
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     * @throws \Doctrine\DBAL\DBALException
     */
    public function deleteAction(Application $app, Request $request)
    {
        try {
            if ($app['security']->isGranted('IS_AUTHENTICATED_FULLY')) {
                $urlModel = new UrlsModel($app);
                $id = (int)$request->get('id', null);
                $url_data = $urlModel->getOneById($id);
                $user_id = $this->currentUserId($app);

                if ($user_id == $url_data['user_id']) {
                    if (isset($url_data)) {
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
                                    'message',
                                    array(
                                        'type' => 'danger',
                                        'content' =>
                                            $app['translator']->trans('URL could not be deleted.')
                                    )
                                );
                            }
                            $app['session']->getFlashBag()->add(
                                'message',
                                array(
                                    'type' => 'success',
                                    'content' =>
                                        $app['translator']->trans('URL deleted.')
                                )
                            );
                            return $app->redirect(
                                $app['url_generator']->generate(
                                    'index',
                                    array(),
                                    301
                                )
                            );
                        }

                        $this->view = array(
                            'form' => $form->createView(),
                            'error' => $app['security.last_error'],
                            'short' => $url_data['short_url'],
                            'id' => $url_data['url_id']
                        );
                        return $app['twig']->render('url/delete.twig', $this->view);
                    } else {
                        $app['session']->getFlashBag()->add(
                            'message',
                            array(
                                'type' => 'danger',
                                'content' =>
                                    $app['translator']->trans('URL could not be found.')
                            )
                        );
                        return $app['twig']->render('url/view.twig', $this->view);

                    }
                } else {
                    $app['session']->getFlashBag()->add(
                        'message',
                        array(
                            'type' => 'danger',
                            'content' =>
                                $app['translator']->trans('This URL is not yours!')
                        )
                    );
                    return $app->redirect(
                        $app['url_generator']->generate(
                            'index',
                            array(),
                            301
                        )
                    );
                }
            } else {
                $app['session']->getFlashBag()->add(
                    'message',
                    array(
                        'type' => 'danger',
                        'content' =>
                            $app['translator']->trans('Log in first!')
                    )
                );
                return $app->redirect(
                    $app['url_generator']->generate(
                        'index',
                        array(),
                        301
                    )
                );
            }
        } catch (Exception $e){
            $app->abort(404, $app['translator']->trans('Could not delete'));

        }
    }
}

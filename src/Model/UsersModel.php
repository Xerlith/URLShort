<?php
/**
 * Users model.
 *
 * @author EPI <epi@uj.edu.pl>
 * @link http://epi.uj.edu.pl
 * @copyright 2015 EPI
 */

namespace Model;

use Doctrine\DBAL\DBALException;
use Silex\Application;
use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UsernameNotFoundException;

/**
 * Class Users.
 *
 * @category Epi
 * @package Model
 * @use Silex\Application
 */
class UsersModel
{
    /**
     * Db object.
     *
     * @access protected
     * @var Silex\Provider\DoctrineServiceProvider $db
     */
    protected $db;

    /**
     * Object constructor.
     *
     * @access public
     * @param Silex\Application|Application $app Silex application
     */
    public function __construct(Application $app)
    {
        $this->db = $app['db'];
    }

    /**
     * Loads user by login.
     *
     * @access public
     * @param string $login User login
     * @throws UsernameNotFoundException
     * @return array Result
     */
    public function loadUserByLogin($login)
    {
        $user = $this->getUserByLogin($login);

        if (!$user || !count($user)) {
            throw new UsernameNotFoundException(
                sprintf('Username "%s" does not exist.', $login)
            );
        }

        $roles = $this->getUserRoles($user['id']);

        if (!$roles || !count($roles)) {
            throw new UsernameNotFoundException(
                sprintf('Username "%s" does not exist.', $login)
            );
        }

        return array(
            'login' => $user['login'],
            'password' => $user['password'],
            'roles' => $roles
        );

    }

    /**
     * Gets user data by login.
     *
     * @access public
     * @param string $login User login
     *
     * @return array Result
     */
    public function getUserByLogin($login)
    {
        try {
            $query = '
              SELECT
                `id`, `login`, `password`, `role_id`
              FROM
                `users`
              WHERE
                `login` = :login
            ';
            $statement = $this->db->prepare($query);
            $statement->bindValue('login', $login, \PDO::PARAM_STR);
            $statement->execute();
            $result = $statement->fetchAll(\PDO::FETCH_ASSOC);
            return !$result ? array() : current($result);
        } catch (\PDOException $e) {
            return array();
        }
    }

    /**
     * Gets user data by their ID.
     * @param $id
     * @return array|mixed
     */
    public function getUserById($id)
    {
        try {
            $query = '
              SELECT
                `id`, `login`, `password`, `role_id`
              FROM
                `users`
              WHERE
                `id` = :id
            ';
            $statement = $this->db->prepare($query);
            $statement->bindValue('id', $id, \PDO::PARAM_STR);
            $statement->execute();
            $result = $statement->fetchAll(\PDO::FETCH_ASSOC);
            return !$result ? array() : current($result);
        } catch (\PDOException $e) {
            return array();
        }
    }

    /**
     * Gets user roles by User ID.
     *
     * @access public
     * @param integer $userId User ID
     *
     * @return array Result
     */
    public function getUserRoles($userId)
    {
        $roles = array();
        try {
            $query = '
                SELECT
                    `roles`.`name` as `role`
                FROM
                    `users`
                INNER JOIN
                    `roles`
                ON `users`.`role_id` = `roles`.`id`
                WHERE
                    `users`.`id` = :user_id
                ';
            $statement = $this->db->prepare($query);
            $statement->bindValue('user_id', $userId, \PDO::PARAM_INT);
            $statement->execute();
            $result = $statement->fetchAll(\PDO::FETCH_ASSOC);
            if ($result && count($result)) {
                $result = current($result);
                $roles[] = $result['role'];
            }
            return $roles;
        } catch (\PDOException $e) {
            return $roles;
        }
    }

    /**
     * Register user.
     *
     * @access public
     * @param array $user User data
     * @return mixed Result
     * @throws DBALException
     */
    public function register($user)
    {
        if (($user['role_id'] === '2')
            && ctype_digit((string)$user['role_id'])
        ) {
            return $this->db->insert('users', $user);
        } else {
            throw new DBALException("Could not register");
        }
    }


    /**
     * Deletes user.
     * @param $id
     * @return array
     * @throws DBALException
     */
    public function deleteUser($id)
    {
        try {
            if (isset($id)
                && ($id != '')
            ) {
                return $this->db->delete('users', array('id' => $id));
            } else {
                return array();
            }
        } catch (Exception $e){
            throw new Exception ("Could not delete user");
        }
    }

    /**
     * Gets all users
     * @return array|mixed
     */
    public function getAllUsers()
    {
        try {
            $query = '
              SELECT
                `id`, `login`, `password`, `role_id`
              FROM
                `users`
              ';
            $statement = $this->db->prepare($query);
            $statement->execute();
            $result = $statement->fetchAll(\PDO::FETCH_ASSOC);
            return !$result ? array() : current($result);
        } catch (\PDOException $e) {
            return array();
        }
    }

    /**
     * Gets users for pagination.
     *
     * @access public
     * @param integer $page Page number
     * @param integer $limit Number of records on single page
     * @return array Result
     */
    public function getPaginatedUsers($page, $limit)
    {
        try {
            $pagesCount = $this->countUserPages($limit);
            $page = $this->getCurrentPageNumber($page, $pagesCount);
            $users = $this->getUserPage($page, $limit);
            return array(
                'users' => $users,
                'paginator' => array('page' => $page, 'pagesCount' => $pagesCount)
            );
        } catch (Exception $e){
            return array();
        }
    }

    /**
     * Get all users on page.
     *
     * @access public
     * @param integer $page Page number
     * @param integer $limit Number of records on single page
     * @return array Result
     */
    public function getUserPage($page, $limit)
    {
        try {
            $query = '
            SELECT
                `id`, `login`, `password`, `role_id`
            FROM
              users
              WHERE
              `role_id` = 2
            LIMIT
              :start, :limit';
            $statement = $this->db->prepare($query);
            $statement->bindValue('start', ($page - 1) * $limit, \PDO::PARAM_INT);
            $statement->bindValue('limit', $limit, \PDO::PARAM_INT);
            $statement->execute();
            $result = $statement->fetchAll(\PDO::FETCH_ASSOC);

            return !$result ? array() : $result;
        } catch (Exception $e){
            return array();
        }
    }

    /**
     * Counts user pages.
     *
     * @access public
     * @param integer $limit Number of records on single page
     * @return integer Result
     */
    public function countUserPages($limit)
    {
        try {
            $pagesCount = 0;
            $query = 'SELECT COUNT(*) as pages_count FROM users';
            $statement = $this->db->prepare($query);
            $statement->execute();
            $result = $statement->fetch(\PDO::FETCH_ASSOC);
            if ($result) {
                $pagesCount = ceil($result['pages_count'] / $limit);
            }
            return $pagesCount;
        } catch (Exception $e){
            return 1;
        }
    }

    /**
     * Returns current page number.
     *
     * @access public
     * @param integer $page Page number
     * @param integer $pagesCount Number of all pages
     * @return integer Page number
     */
    public function getCurrentPageNumber($page, $pagesCount)
    {
        return (($page <= 1) || ($page > $pagesCount)) ? 1 : $page;
    }

    /**
     * Changes user password.
     * @param $user
     * @return mixed
     * @throws DBALException
     */
    public function changePassword($user)
    {
        try {
            if (!empty($user)) {
                return $this->db->update('users', array('password' => $user['password']),
                    array('id' => $user['user_id']));
            } else {
                throw new DBALException("Could not change the password");
            }
        } catch (Exception $e){
            throw new DBALException("Could not change the password");
        }
    }
}

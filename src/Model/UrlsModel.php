<?php
/**
 * Urls model.
 *
 * @author EPI <epi@uj.edu.pl>
 * @link http://epi.uj.edu.pl
 * @copyright 2015 EPI
 */

namespace Model;

use Doctrine\DBAL\DBALException;
use Silex\Application;
use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\Security\Core\Exception\UsernameNotFoundException;

/**
 * Class Urls.
 *
 * @category Epi
 * @package Model
 * @use Silex\Application
 */
class UrlsModel
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
     * @param Silex\Application $app Silex application
     */
    public function __construct(Application $app)
    {
        try {
            $this->db = $app['db'];
        } catch (\Exception $e) {
            echo 'Error! Could not connect.' . $e->getMessage();
        }

    }


    /**
     * Gets urls for pagination.
     *
     * @access public
     * @param integer $page Page number
     * @param integer $limit Number of records on single page
     *
     * @param $user_id
     * @return array Result
     */
    public function getPaginatedUrls($page, $limit, $user_id)
    {
        try {
            $pagesCount = $this->countUrlPages($limit, $user_id);
            $page = $this->getCurrentPageNumber($page, $pagesCount);
            $urls = $this->getUrlPage($page, $limit, $user_id);
            return array(
                'urls' => $urls,
                'paginator' => array('page' => $page, 'pagesCount' => $pagesCount)
            );
        } catch (Exception $e){
            return array();
        }
    }

    /**
     * Gets urls for pagination.
     *
     * @access public
     * @param integer $page Page number
     * @param integer $limit Number of records on single page
     * @return array Result
     */
    public function getPaginatedUrlsAdmin($page, $limit)
    {
        try {
            $pagesCount = $this->countUrlPagesAdmin($limit);
            $page = $this->getCurrentPageNumber($page, $pagesCount);
            $urls = $this->getUrlPageAdmin($page, $limit);
            return array(
                'urls' => $urls,
                'paginator' => array('page' => $page, 'pagesCount' => $pagesCount)
            );
        } catch (Exception $e){
            return array();
        }
    }

    /**
     * Get all urls on page.
     *
     * @access public
     * @param integer $page Page number
     * @param integer $limit Number of records on single page
     * @param $user_id
     * @return array Result
     */
    public function getUrlPage($page, $limit, $user_id)
    {
        try {
            $query = '
            SELECT
              `url_id`, `url`, `short_url`, (
              SELECT COUNT(visit_date) FROM popularity where `popularity`.`url_id`=`url`.`url_id`
              ) as visits
            FROM
              url
            WHERE
              `user_id` = :user_id
            GROUP BY
              `url_id`, `url`, `short_url`
            LIMIT
              :start, :limit';
            $statement = $this->db->prepare($query);
            $statement->bindValue('user_id', $user_id, \PDO::PARAM_INT);
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
     * Gets all URLs from the database
     * @param $page
     * @param $limit
     * @return array Result
     */
    public function getUrlPageAdmin($page, $limit)
    {
        try {
            $query = '
            SELECT
              `url_id`, `url`, `short_url`, (
              SELECT COUNT(visit_date) FROM popularity where `popularity`.`url_id`=`url`.`url_id`
              ) as visits
            FROM
              url
            GROUP BY
              `url_id`, `url`, `short_url`
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
     * Counts url pages.
     *
     * @access public
     * @param integer $limit Number of records on single page
     * @param $user_id
     * @return integer Result
     */
    public function countUrlPages($limit, $user_id)
    {
        try{
            $pagesCount = 0;
            $query = 'SELECT COUNT(*) as pages_count FROM url where user_id = :id';
            $statement = $this->db->prepare($query);
            $statement->bindValue('id', $user_id, \PDO::PARAM_INT);
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
     * Counts pages for Admin URL display
     * @param $limit
     * @return int $pagesCount
     */
    public function countUrlPagesAdmin($limit)
    {
        try {
            $pagesCount = 0;
            $query = 'SELECT COUNT(*) as pages_count FROM url';
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
     * Gets one URL by its short value.
     * @param $short
     * @return mixed
     */
    public function uniqueUrl($short)
    {
        try {
            $query = '
            SELECT
                `url_id`
              FROM
                `url`
              WHERE
                `short_url` = :short';
            $statement = $this->db->prepare($query);
            $statement->bindValue(':short', $short, \PDO::PARAM_INT);
            $statement->execute();
            $state = $statement->fetchAll(\PDO::FETCH_COLUMN);
            return $state;
        } catch (Exception $e) {
            throw new Exception ("Could not perform search");
        }
    }

    /**
     * Gets single Url
     *
     * @access public
     * @param int $url_id
     * @return array Result
     */
    public function getOneById($url_id)
    {
        try {
            $query = 'SELECT `url_id`, `url`, `user_id`, `short_url` FROM `url` WHERE `url_id` = :id';
            $statement = $this->db->prepare($query);
            $statement->bindValue(':id', $url_id, \PDO::PARAM_INT);
            $statement->execute();
            $state = $statement->fetch(\PDO::FETCH_ASSOC);
            return $state;
        } catch (Exception $e){
            return array();
        }
    }

    /**
     * Gets all urls' popularity.
     *
     * @access public
     * @return array popularity
     */

    public function getPopularity()
    {
        try {
            $query = '
              SELECT
                `url`.`url_id`, `visitor_ip`, `visit_date`
              FROM
                `url`
              INNER JOIN
                `popularity`';
            $popularity = $this->db->fetchAll($query);
            return !$popularity ? array() : $popularity;
        } catch (\PDOException $e) {
            return array();
        }

    }

    /**
     * Gets long url by the short value
     * @param $short
     * @return mixed
     */
    public function getLongByShort($short)
    {
        try {
            $query = '
          SELECT
            `url`
          FROM
            `url`
          WHERE
            `short_url` = :short';
            $statement = $this->db->prepare($query);
            $statement->bindValue(':short', $short, \PDO::PARAM_INT);
            $statement->execute();
            $long = $statement->fetch(\PDO::FETCH_ASSOC);
            return $long;
        } catch (Exception $e) {
            return array();
        }

    }


    /**
     * Adds the shortened URL to the database.
     *
     * @access public
     * @param $url
     * @return mixed Result
     */
    public function insertShort($url)
    {
        try {
            return $this->db->insert('url', $url);
        } catch (Exception $e){
            throw new DBALException("Could not insert");
        }
    }


    /**
     * Deletes the URL and its visits.
     *
     * @param $id
     * @throws DBALException
     * @internal param $url
     */

    public function deleteUrl($id)
    {
		try{
			if (isset($id)
				&& ($id != '')
			) {
				$this->db->delete('popularity', array('url_id' => $id));
				return $this->db->delete('url', array('url_id' => $id));
			} 
		} catch (\PDOException $e) {
            throw new DBALException("Could not delete");
        }
	}

    
    /**
     * Gets all URLs by a given user
     * @param $id
     * @return mixed
     */
    public function getAllByUser($id)
    {
		try {
			$query = 'SELECT url_id FROM url where user_id = :id';
			$statement = $this->db->prepare($query);
			$statement->bindValue(':id', $id, \PDO::PARAM_INT);
			$statement->execute();
			$url_ids = $statement->fetchAll(\PDO::FETCH_ASSOC);
			return $url_ids;
		} catch (\PdoException $e) {
			throw $e;
		}

    }

    /**
     * Registers a visit through the shortened URL.
     * @param $visit
     * @return mixed
     */
    public function registerVisit($visit)
    {
        return $this->db->insert('popularity', $visit);
    }

    public function getPaginatedVisits($page, $limit, $url_id)
    {
        try {
            $pagesCount = $this->countVisitPages($limit, $url_id);
            $page = $this->getCurrentPageNumber($page, $pagesCount);
            $visits = $this->getVisitPage($page, $limit, $url_id);
            return array(
                'visits' => $visits,
                'paginator' => array('page' => $page, 'pagesCount' => $pagesCount)
            );
        } catch (Exception $e){
            return array();
        }
    }

    /**
     * Counts the number of visit pages for pagination.
     *
     * @param $limit
     * @param $url_id
     * @return float|int
     */
    public function countVisitPages($limit, $url_id)
    {
        try {
            $pagesCount = 0;
            $query = 'SELECT COUNT(*) as pages_count FROM popularity where url_id = :id';
            $statement = $this->db->prepare($query);
            $statement->bindValue('id', $url_id, \PDO::PARAM_INT);
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
     * Gets a page from visits.
     * @param $page
     * @param $limit
     * @param $url_id
     * @return array
     */
    public function getVisitPage($page, $limit, $url_id)
    {
		try {
			$query = '
				SELECT
				  *
				FROM
				  popularity
				WHERE
				  `url_id` = :url_id
				LIMIT
				  :start, :limit';
			$statement = $this->db->prepare($query);
			$statement->bindValue('url_id', $url_id, \PDO::PARAM_INT);
			$statement->bindValue('start', ($page - 1) * $limit, \PDO::PARAM_INT);
			$statement->bindValue('limit', $limit, \PDO::PARAM_INT);
			$statement->execute();
			$result = $statement->fetchAll(\PDO::FETCH_ASSOC);        
			return !$result ? array() : $result;
		} catch (\PDOException $e) {
			return array();
		}
    }
}

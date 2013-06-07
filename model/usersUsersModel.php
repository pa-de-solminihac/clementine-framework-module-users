<?php
/**
 * usersUsersModel : gestion d'utilisateurs
 *
 * @package
 * @version $id$
 * @copyright
 * @author Pierre-Alexis <pa@quai13.com>
 * @license
 */
class usersUsersModel extends usersUsersModel_Parent
{

    public $table_users                 = 'clementine_users';
    public $table_users_treepaths       = 'clementine_users_treepaths';
    public $table_users_has_groups      = 'clementine_users_has_groups';
    public $table_groups                = 'clementine_users_groups';
    public $table_groups_treepaths      = 'clementine_users_groups_treepaths';
    public $table_groups_has_privileges = 'clementine_users_groups_has_privileges';
    public $table_privileges            = 'clementine_users_privileges';

    public function _init($params = null)
    {
        $this->tables = array($this->table_users => '',
            $this->table_users_has_groups => array('inner join' => "`" . $this->table_users_has_groups . "`.`user_id` = `" . $this->table_users . "`.`id` "),           // determine le champ AI andco_om.id
            $this->table_groups => array('inner join' => "`" . $this->table_users_has_groups . "`.`group_id` = `" . $this->table_groups . "`.`id` "),           // determine le champ AI andco_om.id
        );
        $this->metas['readonly_tables'] = array(
            $this->table_users_has_groups => '',
            $this->table_groups => ''
        );
        $this->group_by = array_merge($this->group_by, array('user_id'));
    }

    /**
     * getAuth : verifie si l'utilsateur est connecte
     * 
     * @access public
     * @return void
     */
    public function getAuth ()
    {
        if (!session_id()) {
            session_start();
        }
        $auth = isset($_SESSION['auth']) ? $_SESSION['auth'] : '';
        if (isset($auth['login']) && strlen($auth['login'])) {
            return $auth;
        } else {
            return false;
        }
    }

    /**
     * tryAuth : tente de se connecter avec le couple login / password passe en parametre
     * 
     * @param mixed $login 
     * @param mixed $password 
     * @access public
     * @return void
     */
    public function tryAuth ($login, $password, $params = null)
    {
        // recupere le grain de sel pour hasher le mot de passe
        $db = $this->getModel('db');
        $sql = 'SELECT salt
            FROM ' . $this->table_users . '
            WHERE login = \'' . $db->escape_string($login) . '\'
            LIMIT 1';
        $stmt = $db->query($sql);
        $result = $db->fetch_array($stmt);
        if ($result) {
            $salt = $result[0];
            $password_hash = hash('sha256', $salt . $password);
            $sql = 'SELECT id, login, is_alias_of
                FROM ' . $this->table_users . ' 
                WHERE login = \'' . $db->escape_string($login) . '\' ';
            // si le parametre bypass_login est positionne, on autorise le login sans password, et check si l'utilisateur est actif
            if (!(isset($params['bypass_login']) && $params['bypass_login'])) {
                $sql .= '
                    AND password = \'' . $db->escape_string($password_hash) . '\'
                    AND active = \'1\' ';
            }
            $sql .= '
                ORDER BY id DESC
                LIMIT 1';
            $stmt = $db->query($sql);
            $result = $db->fetch_array($stmt);
            if ($result && $result['id']) {
                if (!(isset($params['bypass_login']) && $params['bypass_login'])) {
                    // si un parent est suspendu, l'utilisateur ne doit plus pouvoir se connecter
                    $parents = $this->getParents($result['id']); 
                    foreach ($parents as $parent) {
                        if (!$parent['active']) {
                            return false;
                        }
                    }
                }
                $auth = array('id' => $result['id'], 'login' => $result['login']);
                if ($result['is_alias_of']) {
                    $auth['real_id'] = $result['is_alias_of'];
                } else {
                    $auth['real_id'] = $result['id'];
                }
                return $auth;
            } else {
                return false;
            }
        }
    }

    /**
     * getUrlLogin : renvoie l'url pour se logguer
     * 
     * @access public
     * @return void
     */
    public function getUrlLogin ()
    {
        $url_retour = urldecode($this->getModel('fonctions')->ifPost('html', 'url_retour', null, $_SERVER['REQUEST_URI'], 1, 1));
        return __WWW__ . '/users/login?url_retour=' . urlencode($url_retour);
    }

    /**
     * getUrlLogout : renvoie l'url pour se logguer
     * 
     * @access public
     * @return void
     */
    public function getUrlLogout ()
    {
        return __WWW__ . '/users/logout';
    }

    /**
     * needAuth : renvoie vers la page de login l'utilisateur n'est pas loggue
     * 
     * @access public
     * @return void
     */
    public function needAuth ($params = null)
    {
        $auth = $this->getAuth();
        if ($auth) {
            if (isset($params['authorized_uids'])) {
                if (in_array($auth['id'], $params['authorized_uids'])) {
                    return $auth;
                }
            } else {
                return $auth;
            }
        }
        $this->getModel('fonctions')->redirect($this->getUrlLogin());
    }

    /**
     * getPrivileges : renvoie la liste des privileges de l'utilisateur connecté (par défaut) sous forme d'un tableau associatif
     * 
     * @param mixed $user_id : id de l'utilisateur
     * @access public
     * @return void
     */
    public function getPrivileges ($user_id = null)
    {
        if (!$user_id) {
            $auth = $this->getAuth();
            $user_id = (int) $auth['id'];
        }
        if (!$user_id) {
            return array();
        }
        // pas besoin de passer par toutes les tables, on peut raccourcir les traitements en joignant seulement les tables intermediaires
        $db = $this->getModel('db');
        $sql = 'SELECT `' . $this->table_privileges . '`.`privilege` 
                  FROM `' . $this->table_privileges . '` 
                INNER JOIN `' . $this->table_groups_has_privileges . '` 
                            ON `' . $this->table_groups_has_privileges . '`.`privilege_id` = `' . $this->table_privileges . '`.`id` 
                INNER JOIN `' . $this->table_users_has_groups . '` 
                            ON `' . $this->table_users_has_groups . '`.`group_id` = `' . $this->table_groups_has_privileges . '`.`group_id`
                 WHERE `' . $this->table_users_has_groups . '`.`user_id` = \'' . (int) $user_id . '\' ';
        $privileges = array();
        if ($stmt = $db->query($sql)) {
            for (true; $res = $db->fetch_assoc($stmt); true) {
                $privileges[$res['privilege']] = true;
            }
        }
        return $privileges;
    }

    /**
     * needPrivilege : renvoie vrai si l'utilisateur dispose du privilege $privilege
     * 
     * @param mixed $privilege : nom du privilege requis
     * @access public
     * @return void
     */
    public function needPrivilege ($privilege, $needauth = true, $specific_uid = null)
    {
        if ($needauth) {
            $this->needAuth();
        }
        if (!is_array($privilege)) {
            $privilege = array($privilege => true);
        }
        $privileges_granted = $this->getPrivileges($specific_uid);
        $has_privilege = $this->checkPrivileges($privilege, $privileges_granted);
        if (!$has_privilege && $needauth) {
            $this->getModel('fonctions')->redirect($this->getModel('users')->getUrlLogin());
        }
        return $has_privilege;
    }

    /**
     * hasPrivilege : wrapper de needPrivilege
     * 
     * @param mixed $privilege : nom du privilege requis
     * @access public
     * @return void
     */
    public function hasPrivilege ($privilege, $specific_uid = null)
    {
        return $this->needPrivilege($privilege, false, $specific_uid);
    }

    /**
     * getUsers : recupere les couples id, login
     * 
     * @param mixed $id_parent : parent a partir duquel on récupère toutes l'arborescence
     * @access public
     * @return void
     */
    public function getUsers($id = null, $max_depth = 0, $min_depth = 0, $params = null, $type = 'user', $ignore_aliases = true)
    {
        $id = (int) $id;
        $table = $this->table_groups;
        if ($type == 'user') {
            $table = $this->table_users;
        }
        $db = $this->getModel('db');
        $sql = "SELECT `" . $table . "`.*, depth FROM `" . $table . "`
                    INNER JOIN `" . $table . "_treepaths`
                        ON `" . $table . "`.id = `" . $table . "_treepaths`.`descendant`
                    WHERE 1 ";
        if ($id) {
            $sql .= " AND `" . $table . "_treepaths`.`ancestor` = " . (int) $id . " ";
        }
        // ignore les utilisateurs alias
        if ($ignore_aliases) {
            $sql .= "AND is_alias_of IS NULL ";
        }
        // par defaut on renvoie tous les enfants
        if ($max_depth) {
            $sql .= "AND `depth` <= " . (int) $max_depth . " ";
        }
        if ($min_depth) {
            $sql .= "AND `depth` >= " . (int) $min_depth . " ";
        }
        if (isset($params['where'])) {
            $sql .= ' AND ' . $params['where'] . ' ';
        }
        if (isset($params['order_by'])) {
            $sql .= ' ORDER BY ' . $params['order_by'] . ' ';
        } else {
            $sql .= ' ORDER BY `login` ';
        }
        $enfants = array();
        if ($stmt = $db->query($sql)) {
            for (true; $res = $db->fetch_assoc($stmt); true) {
                $enfants[$res['id']] = $res;
            }
        }
        return $enfants;
    }

    /**
     * getUsersByGroup : recupere la liste des id et login en fonction du groupe
     * 
     * @access public
     * @return void
     */
    public function getUsersByGroup ($id_group)
    {
        $id_group = (int) $id_group;
        $db = $this->getModel('db');
        $sql = "SELECT `" . $this->table_users . "`.`id`, `login`
                FROM `" . $this->table_users . "`
                LEFT JOIN `" . $this->table_users_has_groups . "` ON `user_id` = `" . $this->table_users . "`.`id`
                LEFT JOIN `" . $this->table_groups . "` ON `group_id` = `" . $this->table_groups . "`.`id`
                WHERE `" . $this->table_groups . "`.`id` = '" . $id_group . "'
                ORDER BY login ";
        $stmt = $db->query($sql);
        $users = array();
        for (; $res = $db->fetch_array($stmt); true) {
            $users[$res['id']]['login'] = $res['login'];
        }
        return $users;
    }

    /**
     * getUser : récupère les infos d'un user
     * 
     * @param mixed $id 
     * @access public
     * @return void
     */
    public function getUser ($id, $more_details = false)
    {
        $id = (int) $id;
        $db = $this->getModel('db');
        if (!$more_details) {
            $sql = "
                SELECT *
                  FROM `" . $this->table_users . "`
                 WHERE `id` = '" . (int) $id . "'
                 LIMIT 1
            ";
        } else {
            $sql = "
                SELECT * 
                  FROM `" . $this->table_users . "`
                    INNER JOIN `" . $this->table_users_treepaths . "`
                        ON `" . $this->table_users . "`.id = `" . $this->table_users_treepaths . "`.`descendant`
                 WHERE `" . $this->table_users . "`.`id` = '" . (int) $id . "'
                 LIMIT 1
            ";
        }
        $stmt = $db->query($sql);
        $user = $db->fetch_assoc($stmt);
        return $user;
    }

    /**
     * getGroup : récupère le correspondant à un id
     * 
     * @param mixed $id 
     * @access public
     * @return void
     */
    public function getGroup ($id)
    {
        $id = (int) $id;
        $db = $this->getModel('db');
        $sql = "SELECT * FROM `" . $this->table_groups . "`
                 WHERE `id` = '" . $id . "'
                 LIMIT 1";
        $stmt = $db->query($sql);
        if ($stmt) {
            return $db->fetch_assoc($stmt);
        }
        return false;
    }

    public function getGroupParents($id, $max_depth = 0, $min_depth = 0)
    {
        return $this->getParents($id, $max_depth, $min_depth, 'group');
    }

    /**
     * getParents : renvoie les parents en respectant l'ordre remontant dans la hierarchie (ORDER BY depth ASC)
     * 
     * @param mixed $id 
     * @param int $max_depth 
     * @param int $min_depth 
     * @param string $type 
     * @param mixed $ignore_aliases 
     * @access public
     * @return void
     */
    public function getParents($id, $max_depth = 0, $min_depth = 0, $type = 'user', $ignore_aliases = true)
    {
        $id = (int) $id;
        switch ($type) {
            case 'user':
                $table = $this->table_users;
                break;
            default:
                $table = $this->table_groups;
                break;
        }
        $db = $this->getModel('db');
        $sql = "SELECT `" . $table . "`.*, depth FROM `" . $table . "`
                    INNER JOIN `" . $table . "_treepaths`
                        ON `" . $table . "`.id = `" . $table . "_treepaths`.`ancestor`
                    WHERE `" . $table . "_treepaths`.`descendant` = " . (int) $id . "
                    AND `" . $table . "_treepaths`.`ancestor` != `" . $table . "_treepaths`.`descendant` ";
        // ignore les utilisateurs alias
        if ($ignore_aliases) {
            $sql .= " AND is_alias_of IS NULL ";
        }
        // par defaut on renvoie tous les parents
        if ($max_depth) {
            $sql .= "AND `depth` <= " . (int) $max_depth . " ";
        }
        if ($min_depth) {
            $sql .= "AND `depth` >= " . (int) $min_depth . " ";
        }
        $sql .= " ORDER BY depth ";
        $parents = array();
        if ($stmt = $db->query($sql)) {
            for (true; $res = $db->fetch_assoc($stmt); true) {
                $parents[$res['id']] = $res;
            }
        }
        return $parents;
    }

    /**
     * getRootParent : renvoie le parent racine, le plus haut de la hiérarchie 
     * 
     * @param mixed $id 
     * @param string $type 
     * @param mixed $ignore_aliases 
     * @access public
     * @return void
     */
    public function getRootParent($id, $type = 'user', $ignore_aliases = true)
    {
        $id = (int) $id;
        switch ($type) {
            case 'user':
                $table = $this->table_users;
                break;
            default:
                $table = $this->table_groups;
                break;
        }
        $db = $this->getModel('db');
        $sql = "
            SELECT 
                `" . $table . "`.*, depth FROM `" . $table . "` INNER JOIN
                `" . $table . "_treepaths` ON `" . $table . "`.id = `" . $table . "_treepaths`.`ancestor`
            WHERE `" . $table . "_treepaths`.`descendant` = " . (int) $id . " 
        ";
        // ignore les utilisateurs alias
        if ($ignore_aliases) {
            $sql .= " AND is_alias_of IS NULL ";
        }
        // on renvoie le parent racine
        $sql .= " ORDER BY depth DESC LIMIT 1 ";
        if ($stmt = $db->query($sql)) {
            return $db->fetch_assoc($stmt);
        }
        return false;
    }

    /**
     * getGroupsByUser : récupère les groupes d'un user
     * 
     * @param mixed $id 
     * @access public
     * @return void
     */
    public function getGroupsByUser ($id)
    {
        $id = (int) $id;
        $db = $this->getModel('db');
        $sql = 'SELECT `' . $this->table_groups . '`.`group` FROM ' . $this->table_groups . '
                    LEFT JOIN  `' . $this->table_users_has_groups . '` ON `' . $this->table_users_has_groups . '`.`group_id` = `' . $this->table_groups . '`.`id`
                    WHERE `' . $this->table_users_has_groups . '`.`user_id` = \'' . $id . '\'';
        $stmt = $db->query($sql);
        $groups = array();
        for (; $res = $db->fetch_array($stmt); true) {
            $groups[$res['group']] = $res;
        }
        return $groups;
    }

    /**
     * getUserByLogin : récupère les infos d'un user
     * 
     * @param mixed $login 
     * @access public
     * @return void
     */
    public function getUserByLogin ($login)
    {
        $db = $this->getModel('db');
        $sql = 'SELECT * FROM ' . $this->table_users . ' WHERE login = \'' . $db->escape_string($login) . '\' LIMIT 1';
        $stmt = $db->query($sql);
        $user = $db->fetch_assoc($stmt);
        return $user;
    }

    /**
     * addUser : cree un nouvel user et renvoie son id
     * 
     * @param mixed $login 
     * @access public
     * @return void
     */
    public function addUser ($login, $id_parent = null)
    {
        // insertion du user en 2 temps : insertion minimaliste, et update du user dans un 2e temps (moins performant mais factorise le code)
        $user = $this->getUserByLogin($login);
        if (!$user) {
            $db = $this->getModel('db');
            $db->query('START TRANSACTION');
            $date = date('Y-m-d H:i:s');
            $sql  = "INSERT INTO " . $this->table_users . " (
                `login`, `date_creation`)
                VALUES (
                    '" . $db->escape_string($login) . "', '" . $date . "')";
            if (!$stmt = $db->query($sql)) {
                $db->query('ROLLBACK');
                return false;
            }
            $last_insert_id = $db->insert_id();
            $sql  = "INSERT INTO " . $this->table_users_treepaths . " (`ancestor`, `descendant`, `depth`) VALUES ('" . (int) $last_insert_id . "', '" . (int) $last_insert_id . "', 0)";
            if (!$stmt = $db->query($sql)) {
                $db->query('ROLLBACK');
                return false;
            }
            if ($id_parent) {
                if (!$this->updateParent($last_insert_id, $id_parent)) {
                    $db->query('ROLLBACK');
                    return false;
                }
            }
            $db->query('COMMIT');
            return $last_insert_id;
        }
        return false;
    }

    /**
     * modUser : modifie un user avec le tableau associatif passe en parametre, et change la date de modification et le code_confirmation
     * 
     * @param mixed $id 
     * @param mixed $donnees 
     * @access public
     * @return void
     */
    public function modUser ($id, $donnees)
    {
        $id = (int) $id;
        $ns = $this->getModel('fonctions');
        $user = $this->getUser($id);
        if ($user) {
            // ecrase les donnees chargees avec celles mises à jour
            foreach ($donnees as $key => $val) {
                $user[$key] = $val;
            }
            if ($user) {
                $change_pass = 0;
                if (isset($donnees['password']) && $donnees['password'] && $donnees['password'] != 'password') {
                    $change_pass = 1;
                }
                if ($change_pass) {
                    // genere un grain de sel aleatoire
                    $salt               = hash('sha256', (microtime() . rand(0, getrandmax())));
                    // hash le password avec le grain de sel
                    $user['password']   = hash('sha256', $salt . $user['password']);
                }
                // genere un code de confirmation aleatoire
                $code_confirmation  = hash('sha256', (microtime() . rand(0, getrandmax())));
                // met a jour les champs en base de donnees
                $db = $this->getModel('db');
                $db->query('START TRANSACTION');
                $sql  = "UPDATE " . $this->table_users . "
                            SET `login`             = '" . $db->escape_string($user['login']) . "',";
                if ($change_pass) {
                    $sql .= "
                                    `password`          = '" . $db->escape_string($user['password']) . "',
                                    `salt`              = '" . $salt . "',";
                }
                if ($user['is_alias_of']) {
                    $sql .= "
                                `is_alias_of` = '" . $db->escape_string($user['is_alias_of']) . "', ";
                }
                $sql .= "       `code_confirmation` = '" . $code_confirmation . "',
                                `date_modification` = '" . $db->escape_string($user['date_modification']) . "',
                                `active`            = '" . $db->escape_string($user['active']) . "'
                          WHERE `id` = '$id'
                          LIMIT 1 ";
                if (!$stmt = $db->query($sql)) {
                    $db->query('ROLLBACK');
                    return false;
                }
                $parents_directs = $this->getParents($id, 1, 1);
                if ($parents_directs) {
                    $parent_direct = $ns->array_first($parents_directs);
                } else {
                    $parent_direct = array();
                }
                if ((isset($parent_direct['id']) && isset($user['id_parent']) && $parent_direct['id'] != $user['id_parent'])
                    || (!isset($parent_direct['id']) && isset($user['id_parent']) && $user['id_parent'])) {
                    if ($id != $user['id_parent']) {
                        if (!$this->updateParent($id, $user['id_parent'])) {
                            $db->query('ROLLBACK');
                            return false;
                        }
                    }
                }
                $db->query('COMMIT');
                return $user;
            }
        }
        return false;
    }

    /**
     * delUser : supprime un user
     * 
     * @param mixed $id 
     * @access public
     * @return void
     */
    public function delUser ($id)
    {
        $id = (int) $id;
        if ($id) {
            $db = $this->getModel('db');
            $sql  = "DELETE FROM " . $this->table_users_has_groups . " WHERE `user_id` = '$id' ";
            $stmt = $db->query($sql);
            $sql  = "DELETE FROM " . $this->table_users . " WHERE `id` = '$id' LIMIT 1 ";
            $stmt = $db->query($sql);
            if ($db->affected_rows()) {
                return true;
            }
            return false;
        }
    }

    /**
     * updatePassword : remplace le mot de passe de l'utilisateur $login par $password (en le cryptant et en mettant a jour les meta donnees associees)
     * 
     * @param mixed $login 
     * @param mixed $password 
     * @access public
     * @return void
     */
    public function updatePassword ($login, $password)
    {
        $salt               = hash('sha256', (microtime() . rand(0, getrandmax())));
        $code_confirmation  = hash('sha256', (microtime() . rand(0, getrandmax())));
        // hash le password avec le grain de sel
        $password_hash   = hash('sha256', $salt . $password);
        $date = date('Y-m-d H:i:s');
        $db = $this->getModel('db');
        $sql  = "UPDATE " . $this->table_users . "
                    SET `password`          = '" . $db->escape_string($password_hash) . "',
                        `salt`              = '" . $salt . "',
                        `code_confirmation` = '" . $code_confirmation . "',
                        `date_modification` = '" . $db->escape_string($date) . "'
                  WHERE `login` = '" . $db->escape_string($login) . "'
                  LIMIT 1 ";
        $stmt = $db->query($sql);
        return $stmt;
    }

    /**
     * addUserToGroup : Met l'utilisateur dans un groupe
     * 
     * @param mixed $id 
     * @param mixed $groupe 
     * @access public
     * @return void
     */
    public function addUserToGroup ($id, $group)
    {
        $id = (int) $id;
        $user = $this->getUser($id);
        if ($user) {
            $db = $this->getModel('db');
            $sql = "INSERT INTO `" . $this->table_users_has_groups . "` (user_id, group_id)
                    VALUES (" . $id . ", " . $group . ")";
            return $db->query($sql); 
        }
        return false;
    }

    /**
     * getGroupByName : renvoie le du groupe en fonction de son nom
     * 
     * @param mixed $group_name 
     * @access public
     * @return void
     */
    public function getGroupByName ($group)
    {
        $db = $this->getModel('db');
        $sql = "SELECT id
                FROM `" . $this->table_groups . "`
                WHERE `group` = '" . $db->escape_string($group) . "' ";
        $stmt = $db->query($sql); 
        if ($db->num_rows($stmt)) {
            return $db->fetch_assoc($stmt);
        } else {
            return false;
        }
    }

    /**
     * addGroup : cree un nouveau groupe s'il n'existe pas et renvoie son id (false sinon)
     * 
     * @param mixed $name
     * @access public
     * @return void
     */
    public function addGroup ($name, $id_parent = null)
    {
        // insertion du user en 2 temps : insertion minimaliste, et update du user dans un 2e temps (moins performant mais factorise le code)
        $group = $this->getGroupByName($name);
        if (!$group) {
            $db = $this->getModel('db');
            $db->query('START TRANSACTION');
            $sql = "INSERT INTO `" . $this->table_groups . "` (`id`, `group`) VALUES (NULL, '" . $db->escape_string($name) . "')";
            if (!$stmt = $db->query($sql)) {
                $db->query('ROLLBACK');
                return false;
            }
            $last_insert_id = $db->insert_id();
            $sql  = "INSERT INTO " . $this->table_groups_treepaths . " (`ancestor`, `descendant`, `depth`) VALUES ('" . (int) $last_insert_id . "', '" . (int) $last_insert_id . "', 0)";
            if (!$stmt = $db->query($sql)) {
                $db->query('ROLLBACK');
                return false;
            }
            if ($id_parent) {
                if (!$this->updateGroupParent($last_insert_id, $id_parent)) {
                    $db->query('ROLLBACK');
                    return false;
                }
            }
            $db->query('COMMIT');
            return $last_insert_id;
        }
        return false;
    }

    /**
     * modGroup : modifie un groupe avec le tableau associatif passe en parametre
     * 
     * @param mixed $id 
     * @param mixed $donnees 
     * @access public
     * @return void
     */
    public function modGroup ($id, $donnees)
    {
        $id = (int) $id;
        $group = $this->getGroup($id);
        if ($group) {
            $group_original = $group;
            // ecrase les donnees chargees avec celles mises à jour
            foreach ($donnees as $key => $val) {
                $group[$key] = $val;
            }
            if ($group) {
                $db = $this->getModel('db');
                $db->query('START TRANSACTION');
                $sql  = "UPDATE " . $this->table_groups . "
                            SET `group`             = '" . $db->escape_string($group['group']) . "',
                          WHERE `id` = '" . (int) $id . "'
                          LIMIT 1 ";
                if (!$stmt = $db->query($sql)) {
                    $db->query('ROLLBACK');
                    return false;
                }
                if ($group_original['id_parent'] != $group['id_parent']) {
                    $this->updateGroupParent($id, $group['id_parent']);
                }
                $db->query('COMMIT');
                return $group;
            }
        }
        return false;
    }

    public function updateParent($id, $id_parent, $type = 'user')
    {
        if ($id == $id_parent) {
            return false;
        }
        switch ($type) {
            case 'user':
                $table = $this->table_users_treepaths;
                break;
            default:
                $table = $this->table_groups_treepaths;
                break;
        }
        $id = (int) $id;
        $id_parent = (int) $id_parent;
        $db = $this->getModel('db');
        $enfants = $this->getUsers($id, null, 1, null, 'user', false);
        $sql = "DELETE FROM `" . $table . "`
                 WHERE `descendant` = '" . (int) $id . "' 
                   AND `depth` != 0 ";
        if ($stmt = $db->query($sql)) {
            if ($id_parent) {
                // rattache en regénérant l'arborescence
                $sql = "INSERT INTO `" . $table . "` (`ancestor`, `descendant`, `depth`)
                            SELECT ancestor, '" . (int) $id . "', (depth + 1)
                              FROM `" . $table . "` 
                             WHERE `descendant` = '" . (int) $id_parent . "' ";
                $stmt = $db->query($sql);
                // met a jour récursivement TOUS les enfants de cet utilisateur en consequence
                foreach ($enfants as $enfant) {
                    // sinon on MAJ l'ancestor sans changer la profondeur puisqu'elle est relative
                    $this->updateParent($enfant['id'], $id, $type);
                }
                return $stmt;
            }
            return $stmt;
        }
        return false;
    }

    public function updateGroupParent($id, $id_parent)
    {
        return $this->updateParent($id, $id_parent, 'group');
    }

    /**
     * sanitize : recupere et assainit les donnees
     * 
     * @param mixed $insecure_array 
     * @access public
     * @return void
     */
    public function sanitize($insecure_array)
    {
        $ns = $this->getModel('fonctions');
        $secure_array = array();
        if (isset($insecure_array['password']) && ($insecure_array['password'] != 'password')) {
            $secure_array['password'] = $ns->strip_tags($insecure_array['password']);
        } else {
            if (isset($secure_array['password'])) {
                unset($secure_array['password']);
            }
        }
        if (isset($insecure_array['password_conf']) && ($insecure_array['password_conf'] != 'password')) {
            $secure_array['password_conf'] = $ns->strip_tags($insecure_array['password_conf']);
        } else {
            if (isset($secure_array['password_conf'])) {
                unset($secure_array['password_conf']);
            }
        }
        if (isset($insecure_array['login'])) {
            $secure_array['login'] = $ns->strip_tags($insecure_array['login']);
        }
        return $secure_array;
    }

    /**
     * validate : verifie que les donnees conviennent et renvoie un tableau contenant les erreurs
     * 
     * @param mixed $donnees 
     * @access public
     * @return void
     */
    public function validate($donnees, $id = null)
    {
        $ns = $this->getModel('fonctions');
        $err = $this->getHelper('errors');
        if (!isset($donnees['login']) || !strlen($donnees['login']) || !$ns->est_email($donnees['login'])) {
            $err->register_err('missing_fields', 'mail', '- adresse e-mail' . "\r\n");
        }
        if (!$id) {
            if (!isset($donnees['password'])) {
                $err->register_err('missing_fields', 'password', '- mot de passe' . "\r\n");
            }
            if (!isset($donnees['password_conf'])) {
                $err->register_err('missing_fields', 'password_conf', '- confirmation du mot de passe' . "\r\n");
            }
        }
        if (isset($donnees['password'])) {
            if (!$donnees['password'] || $donnees['password'] == 'password') {
                $err->register_err('missing_fields', 'password', '- mot de passe' . "\r\n");
            }
            if (!isset($donnees['password_conf'])) {
                $err->register_err('missing_fields', 'password_conf', '- confirmation du mot de passe' . "\r\n");
            } else {
                if ($donnees['password'] != $donnees['password_conf']) {
                    $err->register_err('missing_fields', 'password', '- mot de passe' . "\r\n");
                    $err->register_err('missing_fields', 'password_conf', '- confirmation du mot de passe' . "\r\n");
                    $err->register_err('password', 'password_mismatch', 'Les champs mot de passe et confirmation du mot de passe sont différents' . "\r\n");
                }
            }
        } else {
            if (isset($donnees['password_conf'])) {
                $err->register_err('missing_fields', 'password_conf', '- confirmation du mot de passe' . "\r\n");
            }
        }
    }

    public function checkPrivileges($required_privileges_tree, $my_privileges = array())
    {
        $condition = $this->checkConditions_translate($required_privileges_tree, $my_privileges);
        return (eval('return ' . $condition . ';'));
    }

    public function checkConditions_translate($required_privileges_tree, $my_privileges = array(), $debug = false)
    {
        $str = '';
        $nb_parentheses = 0;
        if (is_array($required_privileges_tree)) {
            $i = 0;
            foreach ($required_privileges_tree as $privilege => $moreprivileges) {
                if ($i && $str) {
                    $str .= ' || ';
                }
                if ($moreprivileges !== false) {
                    if (substr($privilege, 0, 1) == '!') {
                        $privilege = trim(substr($privilege, 1));
                        if ($debug) {
                            $str .= "!array_key_exists('" . $privilege . "', \$my_privileges)";
                        } else {
                            if (!array_key_exists($privilege, $my_privileges)) {
                                $str .= 'true';
                            } else {
                                $str .= 'false';
                            }
                        }
                    } else {
                        if ($debug) {
                            $str .= "array_key_exists('" . $privilege . "', \$my_privileges)";
                        } else {
                            if (array_key_exists($privilege, $my_privileges)) {
                                $str .= 'true';
                            } else {
                                $str .= 'false';
                            }
                        }
                    }
                } else {
                    if ($debug) {
                        $str .= "!array_key_exists('" . $privilege . "', \$my_privileges)";
                    } else {
                        if (!array_key_exists($privilege, $my_privileges)) {
                            $str .= 'true';
                        } else {
                            $str .= 'false';
                        }
                    }
                }
                if (is_array($moreprivileges)) {
                    if (count($moreprivileges)) {
                        $str .= ' && ';
                        if (count($moreprivileges) > 1) {
                            ++$nb_parentheses;
                            $str .= '(';
                        }
                        $str .= $this->checkConditions_translate($moreprivileges, $my_privileges, $debug);
                    }
                }
                if ($nb_parentheses) {
                    --$nb_parentheses;
                    $str .= ')';
                }
                ++$i;
            }
        }
        return $str;
    }

    /**
     * isParent : returns true if $id_parent is parent of $id_child
     * 
     * @param mixed $id_parent 
     * @param mixed $id_child 
     * @param mixed $depth 
     * @access public
     * @return void
     */
    public function isParent($id_parent, $id_child, $depth = 0)
    {
        $db = $this->getModel('db');
        $sql = "
            SELECT depth
              FROM " . $this->table_users_treepaths . "
             WHERE ancestor   = '" . (int) $id_parent . "'
               AND descendant = '" . (int) $id_child . "'
                AND depth != 0
        ";
        if ($depth) {
            $sql .= "
                AND depth <= " . (int) $depth . "
            ";
        }
        $stmt = $db->query($sql);
        $result = $db->fetch_array($stmt);
        if ($result) {
            return true;
        }
        return false;
    }

    /**
     * isChild returns true if $id_child is child of $id_parent
     * 
     * @param mixed $id_child 
     * @param mixed $id_parent 
     * @param int $depth 
     * @access public
     * @return void
     */
    public function isChild($id_child, $id_parent, $depth = 0)
    {
        return $this->isParent($id_parent, $id_child, $depth);
    }

    /**
     * isAlias : returns true if id_alias and id_user are aliases
     * 
     * @param mixed $user_id 
     * @param mixed $alias_id 
     * @param mixed $strict : returns true only if id_alias === is_alias_of field of user id_user
     * @access public
     * @return void
     */
    public function isAlias($id_alias, $id_user, $strict = false)
    {
        if ($id_alias == $id_user) {
            return false;
        }
        $db = $this->getModel('db');
        $sql = "
            SELECT id
              FROM " . $this->table_users . "
             WHERE (
                (is_alias_of = '" . (int) $id_user . "' AND id = '" . (int) $id_alias. "')
        ";
        if (!$strict) {
            $sql .= "
             OR (is_alias_of = '" . (int) $id_alias . "' AND id = '" . (int) $id_user. "')
             OR ( 
                SELECT id
                  FROM " . $this->table_users . "
                 WHERE (id = '" . (int) $id_alias . "' AND is_alias_of IN (SELECT is_alias_of FROM " . $this->table_users . " WHERE id = '" . (int) $id_user . "'))
            )
            ";
        }
        $sql .= ')';
        $stmt = $db->query($sql);
        $result = $db->fetch_array($stmt);
        if ($result) {
            return true;
        }
        return false;
    }


    /**
     * isSibling : returns true if $id_user is sibling of $id_sibling
     * 
     * @param mixed $id_user 
     * @param mixed $id_sibling 
     * @param mixed $strict : if false, cousins will be considered as siblings too
     * @access public
     * @return void
     */
    public function isSibling($id_user, $id_sibling, $strict = 1)
    {
        if ($id_sibling == $id_user) {
            return false;
        }
        $db = $this->getModel('db');
        $sql = "
            SELECT depth
              FROM " . $this->table_users_treepaths . "
             WHERE descendant = '" . (int) $id_user . "'
               AND CONCAT(ancestor, '-', depth) IN (
                SELECT CONCAT(ancestor, '-', depth)
                  FROM " . $this->table_users_treepaths . "
                 WHERE descendant = '" . (int) $id_sibling . "'
        ";
        if ($strict) {
            $sql .= " AND depth = 1 ";
        }
        $sql .= "
                )
        ";
        if ($strict) {
            $sql .= " AND depth = 1 ";
        }
        $stmt = $db->query($sql);
        $result = $db->fetch_array($stmt);
        if ($result) {
            if (!$this->isAlias($id_user, $id_sibling)) {
                return true;
            }
        }
        return false;
    }

}
?>

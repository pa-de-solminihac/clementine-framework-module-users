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
    public function tryAuth ($login, $password)
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
            $sql = 'SELECT id, login
                FROM ' . $this->table_users . '
                WHERE login = \'' . $db->escape_string($login) . '\'
                AND password = \'' . $db->escape_string($password_hash) . '\'
                AND active = \'1\'
                ORDER BY id DESC
                LIMIT 1';
            $stmt = $db->query($sql);
            $result = $db->fetch_array($stmt);
            if ($result && $result['id']) {
                // si un parent est suspendu, l'utilisateur ne doit plus pouvoir se connecter
                $parents = $this->getParents($result['id']); 
                foreach ($parents as $parent) {
                    if (!$parent['active']) {
                        return false;
                    }
                }
                return array('id' => $result['id'], 'login' => $result['login']);
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
        $ns = $this->getModel('fonctions');
        $url_retour = urldecode($ns->ifPost('html', 'url_retour', null, $_SERVER['REQUEST_URI'], 1, 1));
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
    public function needPrivilege ($privilege, $needauth = true)
    {
        if ($needauth) {
            $this->needAuth();
        }
        if (!is_array($privilege)) {
            $privilege = array($privilege => true);
        }
        $has_privilege = $this->checkPrivileges($privilege, $this->getPrivileges());
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
    public function hasPrivilege ($privilege)
    {
        return $this->needPrivilege($privilege, false);
    }

    /**
     * getUsers : recupere les couples id, login
     * 
     * @param mixed $id_parent : parent a partir duquel on récupère toutes l'arborescence
     * @access public
     * @return void
     */
    public function getUsers ($id_parent = null, $max_depth = 0, $min_depth = 0)
    {
        $db = $this->getModel('db');
        if (!$id_parent) {
            $sql = "SELECT `id`, `login`, 0 AS `depth`, `active`, `date_creation`
                      FROM `" . $this->table_users . "` ";
        } else {
            $sql = "SELECT `id`, `login`, `depth`, `active`, `date_creation`
                      FROM `" . $this->table_users . "`
                        INNER JOIN `" . $this->table_users_treepaths . "`
                            ON `" . $this->table_users . "`.`id` = `" . $this->table_users_treepaths . "`.`descendant`
                     WHERE `" . $this->table_users_treepaths . "`.`ancestor` = '" . (int) $id_parent . "' ";
            if ($max_depth) {
                $sql .= "AND `depth` <= " . (int) $max_depth . " ";
            }
            if ($min_depth) {
                $sql .= "AND `depth` >= " . (int) $min_depth . " ";
            }
        }
        $sql .= 'ORDER BY `login` ';
        $stmt = $db->query($sql);
        $users = array();
        for (; $res = $db->fetch_assoc($stmt); true) {
            $users[$res['id']] = $res;
        }
        return $users;
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
    public function getUser ($id)
    {
        $id = (int) $id;
        $db = $this->getModel('db');
        $sql = "SELECT *
                  FROM `" . $this->table_users . "`
                 WHERE `id` = '" . (int) $id . "'
                 LIMIT 1";
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

    public function getParents($id, $max_depth = 0, $min_depth = 0, $type = 'user')
    {
        switch ($type) {
            case 'user':
                $table = $this->table_users;
                $orig = $this->getUser($id);
                break;
            default:
                $table = $this->table_groups;
                $orig = $this->getGroup($id);
                break;
        }
        $id = (int) $id;
        if ($orig) {
            $db = $this->getModel('db');
            $sql = "SELECT `" . $table . "`.*, depth FROM `" . $table . "`
                        INNER JOIN `" . $table . "_treepaths`
                            ON `" . $table . "`.id = `" . $table . "_treepaths`.`ancestor`
                     WHERE `" . $table . "_treepaths`.`descendant` = " . (int) $id . "
                       AND `" . $table . "_treepaths`.`ancestor` != `" . $table . "_treepaths`.`descendant` ";
            // par defaut on renvoie tous les parents
            if ($max_depth) {
                $sql .= "AND `depth` <= " . (int) $max_depth . " ";
            }
            if ($min_depth) {
                $sql .= "AND `depth` >= " . (int) $min_depth . " ";
            }
            $parents = array();
            if ($stmt = $db->query($sql)) {
                for (true; $res = $db->fetch_assoc($stmt); true) {
                    $parents[$res['id']] = $res;
                }
            }
            return $parents;
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
                $sql .= "       `code_confirmation` = '" . $code_confirmation . "',
                                `date_modification` = '" . $db->escape_string($user['date_modification']) . "',
                                `active`            = '" . $db->escape_string($user['active']) . "'
                          WHERE `id` = '$id'
                          LIMIT 1 ";
                if (!$stmt = $db->query($sql)) {
                    $db->query('ROLLBACK');
                    return false;
                }
                $parent_direct = each($this->getParents($id, 1, 1));
                if ((isset($parent_direct['key']) && isset($user['id_parent']) && $parent_direct['key'] != $user['id_parent'])
                 || (!isset($parent_direct['key']) && isset($user['id_parent']) && $user['id_parent'])) {
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
        $sql = "DELETE FROM `" . $table . "`
                 WHERE `descendant` = '" . (int) $id . "' 
                   AND `depth` != 0 ";
        if ($stmt = $db->query($sql)) {
            if ($id_parent) {
                $sql = "INSERT INTO `" . $table . "` (`ancestor`, `descendant`, `depth`)
                            SELECT ancestor, '" . (int) $id . "', (depth + 1)
                              FROM `" . $table . "` 
                             WHERE `descendant` = '" . (int) $id_parent . "' ";
                return $db->query($sql);
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
            $secure_array['password']            = $ns->ifPost('string', 'password', null, null, 0, 0, 0);
        } else {
            if (isset($secure_array['password'])) {
                unset($secure_array['password']);
            }
        }
        if (isset($insecure_array['password_conf']) && ($insecure_array['password_conf'] != 'password')) {
            $secure_array['password_conf']       = $ns->ifPost('string', 'password_conf', null, null, 0, 0, 0);
        } else {
            if (isset($secure_array['password_conf'])) {
                unset($secure_array['password_conf']);
            }
        }
        if (isset($insecure_array['login'])) {
            $secure_array['login']            = $ns->ifPost('string', 'login', null, null, 0, 0, 0);
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

}
?>

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
    public $table_users_has_groups      = 'clementine_users_has_groups';
    public $table_groups                = 'clementine_users_groups';
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
                return array('id' => $result['id'], 'login' => $result['login']);
            } else {
                sleep(5);
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
        return __WWW__ . '/users/login?url_retour=' . urlencode($_SERVER['REQUEST_URI']);
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
    public function needAuth ()
    {
        $auth = $this->getAuth();
        if (!$auth) {
            $ns = $this->getModel('fonctions')->redirect($this->getUrlLogin());
        }
    }

    /**
     * needPrivilege : renvoie vrai si l'utilisateur dispose du droit $privilege
     * 
     * @param mixed $privilege : nom du droit requis
     * @access public
     * @return void
     */
    public function needPrivilege ($privilege)
    {
        $auth = $this->getAuth();
        if (!$auth) {
            $ns = $this->getModel('fonctions')->redirect($this->getUrlLogin());
        }
        $user_id = (int) $auth['id'];
        // pas besoin de passer par toutes les tables, on peut raccourcir les traitements en joignant seulement les tables intermediaires
        $db = $this->getModel('db');
        $sql = 'SELECT `' . $this->table_users_has_groups . '`.`user_id` 
                  FROM `' . $this->table_users_has_groups . '` 
            INNER JOIN `' . $this->table_groups_has_privileges . '` 
                    ON `' . $this->table_users_has_groups . '`.`group_id` = `' . $this->table_groups_has_privileges . '`.`group_id`
            INNER JOIN `' . $this->table_privileges . '` 
                    ON `' . $this->table_groups_has_privileges . '`.`privilege_id` = `' . $this->table_privileges . '`.`id`
                 WHERE `' . $this->table_users_has_groups . '`.`user_id` = \'' . $user_id . '\' 
                   AND `' . $this->table_privileges . '`.`privilege` = \'' . $db->escape_string($privilege) . '\' LIMIT 1';
        $stmt = $db->query($sql);
        $res = $db->fetch_array($stmt); 
        if (isset($res['user_id']) && $res['user_id'] == $user_id) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * getUsers : recupere la liste des id et login
     * 
     * @access public
     * @return void
     */
    public function getUsers ()
    {
        $db = $this->getModel('db');
        $sql = 'SELECT `id`,
                       `login`
                  FROM ' . $this->table_users . '
                 ORDER BY login ';
        $stmt = $db->query($sql);
        $users = array();
        for (; $res = $db->fetch_array($stmt); true) {
            $users[$res['id']]['login'] = $res['login'];
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
        $db = $this->getModel('db');
        $sql = "SELECT `" . $this->table_users . "`.`id`, `login`
                FROM `" . $this->table_users . "`
                LEFT JOIN `" . $this->table_users_has_groups . "` ON `user_id` = `" . $this->table_users . "`.`id`
                LEFT JOIN `" . $this->table_groups . "` ON `group_id` = `" . $this->table_groups . "`.`id`
                WHERE `" . $this->table_groups . "`.`id` = " . $id_group . "
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
        $sql = 'SELECT * FROM ' . $this->table_users . ' WHERE id = \'' . $id . '\' LIMIT 1';
        $stmt = $db->query($sql);
        $user = $db->fetch_assoc($stmt);
        return $user;
    }

    /**
     * getGroupByUser : récupère les groupes d'un user
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
        $groups = $db->fetch_assoc($stmt);
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
    public function addUser ($login)
    {
        // insertion du user en 2 temps : insertion minimaliste, et update du user dans un 2e temps (moins performant mais factorise le code)
        $user = $this->getUserByLogin($login);
        if (!$user) {
            // $sql  = "START TRANSACTION";
            $db = $this->getModel('db');
            $sql  = 'LOCK TABLES ' . $this->table_users . ' WRITE ';
            $stmt = $db->query($sql);
            $date = date('Y-m-d H:i:s');
            $stmt = $db->query($sql);
            $sql  = "INSERT INTO " . $this->table_users . " (
                `login`, `date_creation`)
                VALUES (
                    '" . $db->escape_string($login) . "', '" . $date . "')";
            $stmt = $db->query($sql);
            $sql  = 'SELECT LAST_INSERT_ID() FROM ' . $this->table_users;
            $stmt = $db->query($sql);
            $last_insert_id_array = $db->fetch_array($stmt);
            $last_insert_id = $last_insert_id_array[0];
            // $sql  = "COMMIT";
            $sql  = "UNLOCK TABLES ";
            $stmt = $db->query($sql);
            return $last_insert_id;
        } else {
            return false;
        }
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
            $user_original = $user;
            // ecrase les donnees chargees avec celles mises à jour
            foreach ($donnees as $key => $val) {
                $user[$key] = $val;
            }
            if ($user) {
                $change_pass = 0;
                if ($user['password'] && $user['password'] != 'password') {
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
                $sql  = "UPDATE " . $this->table_users . "
                            SET `login`             = '" . $db->escape_string($user['login']) . "',";
                if ($change_pass) {
                    $sql .= "
                                    `password`          = '" . $db->escape_string($user['password']) . "',
                                    `salt`              = '" . $salt . "',";
                }
                $sql .= "       `code_confirmation` = '" . $code_confirmation . "',
                                `date_modification` = '" . $db->escape_string($user['date_modification']) . "',
                                `active`            = '" . $db->escape_string($user['active']) . "',
                                `id_parent`         = '" . $db->escape_string($user['id_parent']) . "'
                          WHERE `id` = '$id'
                          LIMIT 1 ";
                $stmt = $db->query($sql);
                return $user_original;
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
     * getGroupByName : renvoie l'id du groupe en fonction de son nom
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
                WHERE `group` = " . $group;
        $stmt = $db->query($sql); 
        if ($db->num_rows($stmt)) {
            $row = $db->fetch_assoc($stmt);
            return $row['id'];
        } else {
            return false;
        }
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
        $secure_array = parent::sanitize($insecure_array);
        if (!is_array($secure_array)) {
            $secure_array = array();
        }
        if (isset($insecure_array['password']) && ($insecure_array['password'] != 'password')) {
            $secure_array['password']            = $ns->ifPost('string', 'password', null, null, 0, 0, 0);
        } else {
            $secure_array['password'] = '';
        }
        if (isset($insecure_array['password_conf']) && ($insecure_array['password_conf'] != 'password')) {
            $secure_array['password_conf']       = $ns->ifPost('string', 'password_conf', null, null, 0, 0, 0);
        } else {
            $secure_array['password_conf'] = '';
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
        $erreurs = parent::validate($donnees);
        if (!is_array($erreurs)) {
            $erreurs = array();
        }
        if (!strlen($donnees['login']) || !$ns->est_email($donnees['login'])) {
            $erreurs[] = 'mail';
        }
        if ((!$id && !strlen($donnees['password'])) || ($donnees['password'] != $donnees['password_conf'])) {
            $erreurs[] = 'password';
        }
        return $erreurs;
    }

}
?>

<?php
/**
 * usersUsersController : gestion d'utilisateurs et d'authentification
 * 
 * @package 
 * @version $id$
 * @copyright 
 * @author Pierre-Alexis <pa@quai13.com> 
 * @license 
 */
class usersUsersController extends usersUsersController_Parent
{

    /**
     * indexAction : liste des utilisateurs
     * 
     * @access public
     * @return void
     */
    public function indexAction($request, $params = null)
    {
        // require privilege "list_users"
        $users = $this->_crud;
        if (!$users->hasPrivilege('list_users')) {
            $this->getModel('fonctions')->redirect($users->getUrlLogin());
        }
        $conf = $this->getModuleConfig();
        // options d'affichage
        if (isset($params['show_groups'])) {
            $this->data['show_groups'] = $params['show_groups'];
        }
        if (isset($params['show_validity'])) {
            $this->data['show_validity'] = $params['show_validity'];
        }
        if (isset($params['show_date'])) {
            $this->data['show_date'] = $params['show_date'];
        }
        if (isset($params['edit_url'])) {
            $this->data['edit_url'] = $params['edit_url'];
        }
        if (isset($conf['simulate_users']) && $users->hasPrivilege('manage_users')) {
            $this->data['simulate_users'] = $conf['simulate_users'];
        }
        $this->hideFields(array(
            $users->table_users . '.password',
            $users->table_users . '.salt',
            $users->table_users . '.code_confirmation',
            $users->table_users . '.active',
            $users->table_users . '.date_creation',
            $users->table_users . '.date_modification'
        ));
        // meilleure présentation de la date de création
        $this->addField('custom_date_creation', null, array(
            'sql_definition' => 'DATE_FORMAT(`date_creation`, "%d/%m/%Y %T")', 
            'custom_order_by' => array (
                'ASC' => 'date_creation ASC',
                'DESC' => 'date_creation DESC'
            )
        ));
        if (isset($params['show_groups'])) {
            $this->addField('Groupes', null, array(
                'sql_definition' =>  'GROUP_CONCAT(DISTINCT `group` ORDER BY `group` SEPARATOR ", ")',
                'custom_search' => '`group`'
            ));
        }
        $ret = parent::indexAction($request, $params);
        $this->data['formtype'] = 'none';
        $this->hideSections(array('readbutton', 'duplicatebutton', 'updatebutton', 'delbutton'));
        return $ret;
    }

    /**
     * loginAction : login de l'utilisateur si POST et redirige l'utilisateur vers la page appelante si OK. Affiche le formulaire de login sinon (si pas ok, ou si pas de POST).
     * 
     * @access public
     * @return void
     */
    public function loginAction($request, $params = null)
    {
        $ns = $this->getModel('fonctions');
        $this->data['message'] = "Connexion requise";
        // Traitement de la demande de login
        $url_retour = $this->getModel('fonctions')->ifGet('html', 'url_retour', null, __WWW__, 1, 1);
        if (!empty($_POST)) {
            // collect the data from the user
            $login    = $ns->strip_tags($request->POST['login']);
            $password = $ns->strip_tags($request->POST['password']);
            if (empty($login)) {
                $this->data['message'] = 'Vous devez fournir vos identifiants pour accéder à cette page';
            } else {
                $this->login($login, $password, array('url_retour' => $url_retour));
            }
        }
        // NOTE : on ne redirige pas si l'utilisateur est deja authentifie...
        // car on créerait une boucle de redirection
        // (puisqu'il n'aurait pas du être redirigé ici)
        // render de la vue
        $this->data['url_retour'] = $url_retour;
    }

    /**
     * simulateAction : permet a un admin de se connecter "en tant que" un autre utilisateur
     * 
     * @param mixed $params 
     * @access public
     * @return void
     */
    public function simulateAction($request, $params = null)
    {
        // accessible aux admin uniquement
        $users = $this->_crud;
        $users->needPrivilege('manage_users');
        // récupère les infos de utilisateur à "usurper"
        $id = $request->get('int', 'id');
        $user_to_be = $users->getUser($id);
        // on n'autorise à simuler un utilisateur que si lui-même n'a pas accès à cette fonctionnalité
        if (!$users->hasPrivilege('manage_users', $user_to_be['id'])) {
            if ($user_to_be && isset($user_to_be['login']) && $user_to_be['login']) {
                $params['bypass_login'] = 1;
                $params['simulate_user'] = 1;
                if (!isset($params['url_retour'])) {
                    $params['url_retour'] = __WWW__;
                }
                $this->login($user_to_be['login'], null, $params);
            }
        }
        return array('dont_getblock' => true);
    }

    public function login($login, $password = null, $params = null)
    {
        $ns = $this->getModel('fonctions');
        // tente l'authentification
        if (isset($params['simulate_user']) && $params['simulate_user']) {
            if (isset($_SESSION['previous_auth'])) {
                unset($_SESSION['previous_auth']);
            }
            $previous_auth = $this->_crud->getAuth();
        }
        $auth = $this->_crud->tryAuth($login, $password, $params);
        // recrée la session si on est en train de simuler un utilisateur
        if ($auth && isset($params['bypass_login']) && $params['bypass_login']) {
            $this->logout();
        }
        // recuperation de l'url retour
        if (!session_id()) {
            session_start();
        } else {
            session_regenerate_id();
        }
        if ($auth) {
            $_SESSION['auth'] = $auth;
            if (isset($params['simulate_user']) && $params['simulate_user']) {
                $_SESSION['previous_auth'] = $previous_auth;
            }
            if (isset($params['url_retour'])) {
                $ns->redirect($params['url_retour']);
            }
        } else {
            // failure: clear auth from session
            if (isset($_SESSION['auth'])) {
                unset($_SESSION['auth']);
            }
            $this->data['message'] = 'Echec de l\'identification.';
            // header 403 puisqu'accès refusé
            header('HTTP/1.0 403 Forbidden');
        }
        return false;
    }

    /**
     * logoutAction : page de logout de l'utilisateur
     * 
     * @access public
     * @return void
     */
    public function logoutAction($request, $params = null)
    {
        $user_to_be = null;
        // prépare les infos si besoin d'un retour a l'utilisateur qu'on etait avant le simulate
        if (isset($_SESSION['previous_auth'])) {
            $user_to_be = $_SESSION['previous_auth'];
            $params['bypass_login'] = 1;
            if (!isset($params['url_retour'])) {
                $params['url_retour'] = __WWW__;
            }
        }
        $this->logout();
        // retour a l'utilisateur qu'on etait avant le simulate
        if ($user_to_be) {
            $this->login($user_to_be['login'], null, $params);
        }
        if (isset($params['url_retour'])) {
            $this->getModel('fonctions')->redirect($params['url_retour']);
        }
    }

    /**
     * logout : logout de l'utilisateur
     * 
     * @access public
     * @return void
     */
    public function logout($params = null)
    {
        if (!session_id()) {
            session_start();
        }
        if (isset($_SESSION['auth'])) {
            unset($_SESSION['auth']);
        }
        session_unset();
    }

    public function rename_fields($params = null)
    {
        $ret = parent::rename_fields($params);
        $this->mapFieldName($this->_crud->table_users . '.login', 'Nom d\'utilisateur');
        $this->mapFieldName($this->_crud->table_users . '.date_creation', 'Date de création');
        $this->mapFieldName('custom_date_creation', 'Date de création');
        return $ret;
    }

    /**
     * editAction : affiche le block d'edition d'un utilisateur
     * 
     * @access public
     * @return void
     */
    public function editAction($request, $params = null)
    {
        $this->_crud->needAuth();
        if (!isset($params['skip_auth'])) {
            $this->_crud->needPrivilege('manage_users');
        }
        $this->data['id'] = $request->get('int', 'id');
        $user = $this->_crud->getUser($this->data['id']);
        $user['password'] = 'password';
        $this->data['user'] = $user;
        $this->getModel('cssjs')->register_foot('users-js_submit', $this->getBlockHtml('users/js_submit', $this->data));
    }

    /**
     * addAction : affiche le block de creation d'un utilisateur
     * 
     * @access public
     * @return void
     */
    public function addAction($request, $params = null)
    {
        $this->_crud->needAuth();
        $this->data['id'] = $request->get('int', 'id');
        $this->data['user'] = $this->_crud->getUser($this->data['id']);
    }

    /**
     * deleteAction : prepare les variables pour la suppression d'un user
     * 
     * @access public
     * @return void
     */
    public function deleteAction($request, $params = null)
    {
        if (isset($params['user_id'])) {
            $id = $params['user_id'];
        } else {
            $id = $request->get('int', 'id');
        }
        $users = $this->_crud;
        $auth = $users->needAuth();
        // recupere les données
        $user = $users->getUser($id);
        if ($user['id'] && !($users->hasPrivilege('manage_users') || isset($params['allow_login_modification']))) {
            $users->needPrivilege('manage_users');
        }
        $success = false;
        if ($id != $auth['id']) {
            // suppression du user
            if ($id) {
                $success = $this->_crud->delUser($id);
            }
        }
        // messages
        if (!$success) {
            $this->data['message'] = "Impossible de supprimer cet utilisateur";
        }
    }

    /**
     * oubliAction : affichage du formulaire pour mot de passe oublie
     * 
     * @access public
     * @return void
     */
    public function oubliAction($request, $titre = null, $params = null)
    {
        $ns = $this->getModel('fonctions');
        if (!empty($_POST)) {
            $login = $ns->strip_tags($request->POST['login']);
            if ($ns->est_email($login)) {
                $user = $this->_crud->getUserByLogin($login);
            } else {
                $this->data['error'] = 'Vous devez fournir l\'adresse e-mail utilisée lors de votre inscription.';
                $user = 0;
            }
            // verifie que l'utilisateur n'est pas suspendu
            if (!$user['active']) {
                $this->data['error'] = 'Ce compte est suspendu.';
                $user = 0;
            }
            // securite
            if ($user) {
                $login = $user['login'];
                $lien_confirmation = __WWW__ . '/users/renew?l=' . base64_encode($login) . '&c=' . hash('sha256', $user['code_confirmation']);
                // envoie un mail pour proposer le changement de mot de passe
                if ($titre === null) {
                    $titre = Clementine::$config['clementine_global']['site_name'] . " : demande de renouvellement de votre mot de passe ";
                }
                $contenu = $this->getBlockHtml('users/mail_oubli_pass', array('user' => $user, 'lien_confirmation' => $lien_confirmation));
                $contenu_texte  = $ns->strip_tags(str_replace('<hr />', '------------------------------',
                                                  str_replace('<br />', "\n", $contenu))) . "\n";
                $to = $login;
                $from = Clementine::$config['clementine_global']['email_exp'];
                $fromname = Clementine::$config['clementine_global']['site_name'];
                if ($ns->envoie_mail($to,
                                     $from,
                                     $fromname,
                                     $titre,
                                     $contenu_texte,
                                     $contenu)) {
                    $this->data['message'] = 'Un e-mail contenant les instructions à suivre pour renouveler votre mot de passe vous a été envoyé. <br />N\'oubliez pas de consulter également votre courier indésirable...';
                }
            }
        }
    }

    /**
     * renewAction : affichage de le block confirmant le renouvellement du mot de passe
     * 
     * @access public
     * @return void
     */
    public function renewAction($request, $titre = null, $params = null)
    {
        $ns = $this->getModel('fonctions');
        $code = $request->get('string', 'c');
        $login = base64_decode($request->get('string', 'l'));
        if ($ns->est_email($login)) {
            $user = $this->_crud->getUserByLogin($login);
            if ($user) {
                $hash_confirmation = hash('sha256', $user['code_confirmation']);
                if ($hash_confirmation == $code) {
                    // renouvelle les identifiants, change le code de confirmation, et envoie un mot de passe a l'utilisateur
                    $newpass = substr(hash('sha256', hash('sha256', (microtime() . rand(0, getrandmax())))), 0, 8);
                    if ($this->_crud->updatePassword($login, $newpass)) {
                        if ($titre === null) {
                            $titre = Clementine::$config['clementine_global']['site_name'] . " : renouvellement de votre mot de passe";
                        }
                        $contenu = $this->getBlockHtml('users/mail_renew_pass', array('user' => $user, 'newpass' => $newpass));
                        $contenu_texte  = $ns->strip_tags(str_replace('<hr />', '------------------------------',
                                                          str_replace('<br />', "\n", $contenu))) . "\n";
                        $to = $login;
                        $from = Clementine::$config['clementine_global']['email_exp'];
                        $fromname = Clementine::$config['clementine_global']['site_name'];
                        if (!$ns->envoie_mail($to,
                                              $from,
                                              $fromname,
                                              $titre,
                                              $contenu_texte,
                                              $contenu)) {
                            $this->data['error'] = 'Impossible d\'envoyer le mail de renouvellement du mot de passe';
                        }
                    } else {
                        $this->data['error'] = 'Impossible de renouveler le mot de passe';
                    }
                } else {
                    $this->data['error'] = 'Code invalide';
                }
            }
        }
    }

    /**
     * createAction : affiche le formulaire de creation d'utilisateur
     * 
     * @access public
     * @return void
     */
    public function createAction($request, $params = null)
    {
        if (!isset($params['skip_auth'])) {
            $this->_crud->needPrivilege('manage_users');
        }
        $this->getModel('cssjs')->register_foot('users-js_submit', $this->getBlockHtml('users/js_submit', $this->data));
    }

    /**
     * updateAction : en attente de la fin de la migration de USERS vers CRUD, on desactive ça par sécurité
     * 
     * @param mixed $request 
     * @param mixed $params 
     * @access public
     * @return void
     */
    public function updateAction($request, $params = null)
    {
        $this->_crud->needPrivilege('manage_users');
        return array('dont_getblock' => true);
    }

    /**
     * readAction : en attente de la fin de la migration de USERS vers CRUD, on desactive ça par sécurité
     * 
     * @param mixed $request 
     * @param mixed $params 
     * @access public
     * @return void
     */
    public function readAction($request, $params = null)
    {
        $this->_crud->needPrivilege('manage_users');
        return array('dont_getblock' => true);
    }

    /**
     * deletetmpfileAction : en attente de la fin de la migration de USERS vers CRUD, on desactive ça par sécurité
     * 
     * @param mixed $request 
     * @param mixed $params 
     * @access public
     * @return void
     */
    public function deletetmpfileAction($request, $params = null)
    {
        if (!isset($params['skip_auth'])) {
            $this->_crud->needPrivilege('manage_users');
        }
        return array('dont_getblock' => true);
    }

    /**
     * validuserAction : verifie les donnees postees et ajoute ou modifie un utilisateur
     * 
     * @access public
     * @return void
     */
    public function validuserAction($request, $params = null)
    {
        if (!isset($params['skip_auth'])) {
            $this->_crud->needPrivilege('manage_users');
        }
        $users = $this->_crud;
        if ($request->POST) {
            $ret = $this->create_or_update_user($request, $params);
            // envoie les emails de confirmation / notification / activation
            // TODO: ajouter la gestion de l'activation
            if (isset($ret['user']) && isset($ret['isnew'])) {
                $this->sendmail_confirmation($ret);
                $this->sendmail_notification($ret);
                $this->sendmail_activation($ret);
            }
            // tente l'autologin si necessaire
            $auth = $users->getAuth();
            // pas si on est déjà connecté !
            if (isset($ret['isnew']) && $ret['user']['active'] && !$auth) {
                $this->login($ret['user']['login'], $ret['isnew']['password']);
            }
            if (is_array($ret)) {
                $this->data = array_merge_recursive((array) $this->data, $ret);
            } else {
                $this->data = (array) $this->data;
            }
            $url_retour = null;
            if (isset($params['url_retour'])) {
                $url_retour = $params['url_retour'];
            }
            $errors = array();
            if (isset($this->data['errors'])) {
                $errors = $this->data['errors'];
            }
            return $this->handle_errors($errors, $url_retour);
        }
    }

    public function validuser_okAction($request, $params = null)
    {
        $ns = $this->getModel('fonctions');
        $users = $this->_crud;
        if ($users->hasPrivilege('manage_users')) {
            $url_retour = __WWW__ . '/users/index';
        } else {
            $id = $request->get('int', 'id');
            $this->data['user'] = $users->getUser($id);
        }
        if (isset($params['url_retour'])) {
            $ns->redirect($params['url_retour']);
        }
    }

    public function handle_errors($errors, $url_retour = null)
    {
        $request = $this->getRequest();
        $ns = $this->getModel('fonctions');
        if (!count($errors)) {
            if (!$url_retour) {
                $url_retour = __WWW__ . '/users/validuser_ok';
                if (isset($this->data['user']['id'])) {
                    $url_retour = $ns->mod_param($url_retour, 'id', $this->data['user']['id']);
                }
            }
            if (isset($this->data['isnew']) && ($this->data['isnew'])) {
                $url_retour = $ns->mod_param($url_retour, 'isnew', 1);
            }
            if ($request->AJAX) {
                echo '2';
                echo $url_retour;
                return array('dont_getblock' => true);
            } else {
                $ns->redirect($url_retour);
            }
        } else {
            if ($request->AJAX) {
                // valeur de retour pour AJAX
                echo '1';
                $this->getBlock('users/validuser', $this->data);
                return array('dont_getblock' => true);
            }
        }
    }

    public function create_or_update_user($request, $params = null)
    {
        $ns = $this->getModel('fonctions');
        $err = $this->getHelper('errors');
        $users = $this->_crud;
        // recupere les parametres
        $id = $request->post('int', 'id');
        // recuperation des donnees et assainissement
        $donnees = $users->sanitize($request->POST);
        // la modification du login requiert le privilege manage_users (ou un bypass dans $params)
        if ($id && isset($donnees['login'])) {
            $user = $users->getUser($id);
            if ($user['login'] != $donnees['login']) {
                if (!($users->hasPrivilege('manage_users') || isset($params['allow_login_modification']))) {
                    $ns->redirect($users->getUrlLogin());
                } else {
                    // verifie que l'utilisateur n'existe pas déjà
                    $already_user = $users->getUserByLogin($donnees['login']);
                    if ($already_user) {
                        $err->register_err('user', 'user_already_exists', "L'utilisateur existe déjà\r\n");
                    }
                    $erreurs = $err->get();
                    if (count($erreurs)) {
                        return array('errors' => $erreurs);
                    }
                }
            }
        }
        $donnees['date_modification']       = date('Y-m-d H:i:s');
        $auth = $users->getAuth();
        // on rattache l'utilisateur si c'est une création par un utilisateur connecté
        if (isset($auth['login']) && strlen($auth['login']) && !isset($user['id'])) {
            // si c'est un adjoint on le rattache au meme parent que le compte maitre
            if (isset($params['adjoint']) && $params['adjoint']) {
                $parents_directs = $users->getParents($auth['id'], 1, 1);
                $parent_direct = false;
                if (count($parents_directs)) {
                    $parent_direct = $ns->array_first($parents_directs);
                }
                if ($parent_direct && isset($parent_direct['id']) && $parent_direct['id']) {
                    $donnees['id_parent'] = $parent_direct['id'];
                } else {
                    // pas de parent, on ne rattache pas
                    $donnees['id_parent'] = 0;
                }
            } else {
                $donnees['id_parent'] = $auth['id'];
            }
        } else {
            if (!isset($user['id'])) {
                $donnees['id_parent'] = 0;
            } else {
                // en cas de modif, on garde l'id parent existant
                $parents_directs = $users->getParents($user['id'], 1, 1);
                $parent_direct = false;
                if (count($parents_directs)) {
                    $parent_direct = $ns->array_first($parents_directs);
                }
                if ($parent_direct && isset($parent_direct['id']) && $parent_direct['id']) {
                    $donnees['id_parent'] = $parent_direct['id'];
                }
            }
        }
        // verification des donnees requises
        $users->validate($donnees, $id);
        $erreurs = $err->get();
        $ret = array();
        if (!count($erreurs)) {
            if (!$id) {
                $id = $users->addUser($donnees['login']);
                if ($id) {
                    $ret['isnew'] = array('password' => $donnees['password']);
                }
            }
            if (!$id) {
                $err->register_err('user', 'user_already_exists', 'Cet utilisateur existe déjà' . "\r\n");
            } else {
                $user = $users->modUser($id, $donnees);
                if (!$user) {
                    $err->register_err('user', 'user_modification_failed', 'Impossible de modifier cet utilisateur' . "\r\n");
                } else {
                    $ret['user'] = $user;
                }
            }
        }
        $erreurs = $err->get();
        if (count($erreurs)) {
            $ret['errors'] = $erreurs;
        }
        return $ret;
    }

    public function sendmail_confirmation($user, $titre = null, $params = null)
    {
        $conf = $this->getModuleConfig();
        $ns = $this->getModel('fonctions');
        if ($conf['send_account_confirmation'] && isset($user['isnew'])) {
            if ($titre === null) {
                $titre = Clementine::$config['clementine_global']['site_name'] . " : votre nouveau compte";
            }
            $contenu = $this->getBlockHtml('users/mail_confirmation', $user);
            $contenu_texte  = $ns->strip_tags(str_replace('<hr />', '------------------------------',
                                              str_replace('<br />', "\n", $contenu))) . "\n";
            $to = $user['user']['login'];
            $from = Clementine::$config['clementine_global']['email_exp'];
            $fromname = Clementine::$config['clementine_global']['site_name'];
            if ($ns->envoie_mail($to,
                                 $from,
                                 $fromname,
                                 $titre,
                                 $contenu_texte,
                                 $contenu)) {
                return true;
            }
        }
        return false;
    }

    public function sendmail_notification($user, $titre = null, $params = null)
    {
        $conf = $this->getModuleConfig();
        $ns = $this->getModel('fonctions');
        if ($conf['send_account_notification']) {
            if ($titre === null) {
                $titre = Clementine::$config['clementine_global']['site_name'] . " : inscription d'un nouvel utilisateur";
            }
            $contenu = $this->getBlockHtml('users/mail_notification', $user);
            $contenu_texte  = $ns->strip_tags(str_replace('<hr />', '------------------------------',
                                              str_replace('<br />', "\n", $contenu))) . "\n";
            $to = Clementine::$config['clementine_global']['email_prod'];
            $from = Clementine::$config['clementine_global']['email_exp'];
            $fromname = Clementine::$config['clementine_global']['site_name'];
            if ($ns->envoie_mail($to,
                                 $from,
                                 $fromname,
                                 $titre,
                                 $contenu_texte,
                                 $contenu)) {
                return true;
            }
        }
        return false;
    }

    public function sendmail_activation($user, $titre = null, $params = null)
    {
        $conf = $this->getModuleConfig();
        if ($conf['send_account_activation']) {
            // TODO: envoi d'un mail d'activation avec une URL permettant d'activer le compte
        }
    }

}
?>

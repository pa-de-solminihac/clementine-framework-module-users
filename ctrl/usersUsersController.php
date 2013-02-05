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
     * loginAction : login de l'utilisateur si POST et redirige l'utilisateur vers la page appelante si OK. Affiche le formulaire de login sinon (si pas ok, ou si pas de POST).
     * 
     * @access public
     * @return void
     */
    function loginAction ($params = null)
    {
        $ns = $this->getModel('fonctions');
        $this->data['message'] = "Connexion requise";
        // Traitement de la demande de login
        $url_retour = $ns->ifGet('html', 'url_retour', null, __WWW__, 1, 1);
        if (!empty($_POST)) {
            // collect the data from the user
            $login    = $ns->strip_tags($ns->ifPost('string', 'login'));
            $password = $ns->strip_tags($ns->ifPost('string', 'password'));
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

    public function login($login, $password, $params = null)
    {
        $ns = $this->getModel('fonctions');
        // tente l'authentification
        $auth = $this->getModel('users')->tryAuth($login, $password);
        // recuperation de l'url retour
        if (!session_id()) {
            session_start();
        }
        if ($auth) {
            $_SESSION['auth'] = $auth;
            if (isset($params['url_retour'])) {
                $ns->redirect($params['url_retour']);
            }
        } else {
            // failure: clear auth from session
            if (isset($_SESSION['auth'])) {
                unset($_SESSION['auth']);
            }
            // securite contre le bruteforce, ca mange pas de pain
            sleep(3);
            $this->data['message'] = 'Echec de l\'identification.';
        }
        return false;
    }

    /**
     * logoutAction : logout de l'utilisateur
     * 
     * @access public
     * @return void
     */
    function logoutAction ($params = null)
    {
        if (!session_id()) {
            session_start();
        }
        if (isset($_SESSION['auth'])) {
            unset($_SESSION['auth']);
        }
        session_unset();
    }

    /**
     * indexAction : liste des utilisateurs
     * 
     * @access public
     * @return void
     */
    function indexAction ($params = null)
    {
        // require privilege "list_users"
        $users = $this->getModel('users');
        if (!$users->needPrivilege('list_users', false)) {
            $this->getModel('fonctions')->redirect($users->getUrlLogin());
        }
        $cssjs = $this->getModel('cssjs');
        // jQuery
        if (Clementine::$config['module_jstools']['use_google_cdn']) {
            $cssjs->register_js('jquery', array('src' => 'https://ajax.googleapis.com/ajax/libs/jquery/1.6.4/jquery.min.js'));
        } else {
            $cssjs->register_js('jquery', array('src' => __WWW_ROOT_JSTOOLS__ . '/skin/jquery/jquery.min.js'));
        }
        // dataTables : sortable tables
        $cssjs->register_css('jquery.dataTables',  array('src' => __WWW_ROOT_JSTOOLS__ . '/skin/js/jquery.dataTables/dataTables.css'));
        $cssjs->register_js('jquery.dataTables', array('src' => __WWW_ROOT_JSTOOLS__ . '/skin/js/jquery.dataTables/jquery.dataTables.min.js'));
        $cssjs->register_foot('clementine_jstools-datatables', $this->getBlockHtml('jstools/js_datatables', $this->data));
        if (isset($params['show_groups'])) {
            $this->data['show_groups'] = $params['show_groups'];
        }
        $this->data['users'] = $users->getUsers();
    }

    /**
     * editAction : affiche le block d'edition d'un utilisateur
     * 
     * @access public
     * @return void
     */
    function editAction ($params = null)
    {
        $this->getModel('users')->needAuth();
        $ns = $this->getModel('fonctions');
        $this->data['id'] = $ns->ifGet('int', 'id');
        $user = $this->getModel('users')->getUser($this->data['id']);
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
    function addAction ($params = null)
    {
        $this->getModel('users')->needAuth();
        $ns = $this->getModel('fonctions');
        $this->data['id'] = $ns->ifGet('int', 'id');
        $this->data['user'] = $this->getModel('users')->getUser($this->data['id']);
    }

    /**
     * deleteAction : prepare les variables pour la suppression d'un user
     * 
     * @access public
     * @return void
     */
    function deleteAction ($params = null)
    {
        $this->getModel('users')->needAuth();
        $auth = $this->getModel('users')->getAuth();
        $ns = $this->getModel('fonctions');
        $id = $ns->ifGet('int', 'id');
        $success = false;
        if ($id != $auth['id']) {
            // suppression du user
            if ($id) {
                $success = $this->getModel('users')->delUser($id);
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
    public function oubliAction ($titre = null, $params = null)
    {
        $ns = $this->getModel('fonctions');
        if (!empty($_POST)) {
            $login = $ns->ifPost('string', 'login');
            if ($ns->est_email($login)) {
                $user = $this->getModel('users')->getUserByLogin($login);
            } else {
                sleep(3);
                $this->data['error'] = 'Vous devez fournir l\'adresse e-mail utilisée lors de votre inscription.';
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
                    $this->data['message'] = 'Un e-mail contenant les instructions à suivre pour renouveler votre mot de passe vous a été envoyé. ';
                    // temporisation lors de l'envoi des mails
                    sleep(3);
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
    public function renewAction ($titre = null, $params = null)
    {
        $ns = $this->getModel('fonctions');
        $code = $ns->ifGet('string', 'c');
        $login = base64_decode($ns->ifGet('string', 'l'));
        if ($ns->est_email($login)) {
            $user = $this->getModel('users')->getUserByLogin($login);
            if ($user) {
                $hash_confirmation = hash('sha256', $user['code_confirmation']);
                sleep(3);
                if ($hash_confirmation == $code) {
                    // renouvelle les identifiants, change le code de confirmation, et envoie un mot de passe a l'utilisateur
                    $newpass = substr(hash('sha256', hash('sha256', (microtime() . rand(0, getrandmax())))), 0, 8);
                    if ($this->getModel('users')->updatePassword($login, $newpass)) {
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
    function createAction ($params = null)
    {
        $this->getModel('cssjs')->register_foot('users-js_submit', $this->getBlockHtml('users/js_submit', $this->data));
    }

    /**
     * validuserAction : verifie les donnees postees et ajoute ou modifie un utilisateur
     * 
     * @access public
     * @return void
     */
    function validuserAction ($params = null)
    {
        $request = $this->getRequest();
        $users = $this->getModel('users');
        if ($request['POST']) {
            $ret = $this->create_or_update_user($params);
            // envoie les emails de confirmation / notification / activation
            // TODO: ajouter la gestion de l'activation
            if (isset($ret['user']) && isset($ret['isnew'])) {
                $this->sendmail_confirmation($ret);
                $this->sendmail_notification($ret);
                $this->sendmail_activation($ret);
            }
            // tente l'autologin si necessaire
            $auth = $users->getAuth();
            if (isset($ret['isnew']) && $ret['user']['active'] && !$auth) {
                $this->login($ret['user']['login'], $ret['isnew']['password']);
            }
            $this->data = array_merge_recursive((array) $this->data, $ret);
            return $this->handle_errors();
        }
    }

    public function validuser_okAction ($params = null)
    {
        $ns = $this->getModel('fonctions');
        $users = $this->getModel('users');
        if ($users->needPrivilege('manage_users', false)) {
            $url_retour = __WWW__ . '/users/index';
            if (isset($params['url_retour'])) {
                $url_retour = $params['url_retour'];
            }
            $ns->redirect($url_retour);
        } else {
            $id = $ns->ifGet('int', 'id');
            $this->data['user'] = $users->getUser($id);
        }
    }

    public function handle_errors($url_retour = null)
    {
        $errors = array();
        if (isset($this->data['errors'])) {
            $errors = $this->data['errors'];
        }
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
            if ($request['AJAX']) {
                echo '2';
                echo $url_retour;
                return array('dont_getblock' => true);
            } else {
                $ns->redirect($url_retour);
            }
        } else {
            if ($request['AJAX']) {
                // valeur de retour pour AJAX
                echo '1';
                $this->getBlock('users/validuser', $this->data);
                return array('dont_getblock' => true);
            }
        }
    }

    public function create_or_update_user($params = null)
    {
        $ns = $this->getModel('fonctions');
        $users = $this->getModel('users');
        // recupere les parametres
        $id = $ns->ifPost('int', 'id');
        // recuperation des donnees et assainissement
        $request = $this->getRequest();
        $donnees = $users->sanitize($request['POST']);
        // la modification du login requiert le privilege manage_users
        if ($id && isset($donnees['login'])) {
            $user = $users->getUser($id);
            if ($user['login'] != $donnees['login'] && !$users->needPrivilege('manage_users', false)) {
                $ns->redirect($users->getUrlLogin());
            }
        }
        $donnees['date_modification']       = date('Y-m-d H:i:s');
        $auth = $users->getAuth();
        if (isset($auth['login']) && strlen($auth['login'])) {
            $donnees['id_parent'] = $auth['id'];
        } else {
            $donnees['id_parent'] = 0;
        }
        // verification des donnees requises
        $err = $this->getHelper('errors');
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
                    // temporisation lors de l'envoi des mails
                    sleep(3);
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

    public function sendmail_activation($user, $titre = null, $params = null)
    {
        $conf = $this->getModuleConfig();
        if ($conf['send_account_activation']) {
            // TODO: envoi d'un mail d'activation avec une URL permettant d'activer le compte
        }
    }

}
?>

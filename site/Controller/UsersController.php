<?php
App::uses('AppController', 'Controller');
/**
 * Users Controller
 *
 * @property User $User
 * @property PaginatorComponent $Paginator
 * @property SessionComponent $Session
 */
class UsersController extends AppController {

	public $helpers = array('Session');
/**
 * Components
 *
 * @var array
 */
	public $components = array(
        'Paginator',
        'Auth' => array(
            'authenticate' => array('Form' => array('userModel' => 'User',
                                                    'fields' => array(
                                                                'username' => 'username',
                                                                'password' => 'password'
                                                                )
                                                    )
                                    ),
            'loginRedirect' => "",
            'logoutRedirect' => array('controller' => 'users', 'action' => 'login'),
            'authorize' => array('Controller')
        )
    );

	public function beforeFilter(){
        parent::beforeFilter();
        $this->Auth->allow('admin_index', 'admin_view', 'admin_edit', 'admin_add', 'admin_delete', 'changepassword', 'logout');
    }


/**
 * admin_index method
 *
 * @return void
 */
	public function admin_index() {
		$this->User->recursive = 0;
		$this->set('users', $this->Paginator->paginate());
	}

/**
 * admin_view method
 *
 * @throws NotFoundException
 * @param string $id
 * @return void
 */
	public function admin_view($id = null) {
		if (!$this->User->exists($id)) {
			throw new NotFoundException(__('Invalid user'));
		}
		$options = array('conditions' => array('User.' . $this->User->primaryKey => $id));
		$this->set('user', $this->User->find('first', $options));
	}

/**
 * admin_add method
 *
 * @return void
 */
	public function admin_add() {
		if ($this->request->is('post')) {
			$this->User->create();
			$this->request->data['User']['password'] = AuthComponent::password($this->request->data['User']['password']);
			if ($this->User->save($this->request->data)) {
				$this->Session->setFlash(__('The user has been saved.'));
				return $this->redirect(array('action' => 'index'));
			} else {
				$this->Session->setFlash(__('The user could not be saved. Please, try again.'));
			}
		}
	}

/**
 * admin_edit method
 *
 * @throws NotFoundException
 * @param string $id
 * @return void
 */
	public function admin_edit($id = null) {
		if (!$this->User->exists($id)) {
			throw new NotFoundException(__('Invalid user'));
		}
		if ($this->request->is(array('post', 'put'))) {
			if ($this->User->save($this->request->data)) {
				$this->Session->setFlash(__('The user has been saved.'));
				return $this->redirect(array('action' => 'index'));
			} else {
				$this->Session->setFlash(__('The user could not be saved. Please, try again.'));
			}
		} else {
			$options = array('conditions' => array('User.' . $this->User->primaryKey => $id));
			$this->request->data = $this->User->find('first', $options);
		}
	}

/**
 * admin_delete method
 *
 * @throws NotFoundException
 * @param string $id
 * @return void
 */
	public function admin_delete($id = null) {
		$this->User->id = $id;
		if (!$this->User->exists()) {
			throw new NotFoundException(__('Invalid user'));
		}
		$this->request->allowMethod('post', 'delete');
		if ($this->User->delete()) {
			$this->Session->setFlash(__('The user has been deleted.'));
		} else {
			$this->Session->setFlash(__('The user could not be deleted. Please, try again.'));
		}
		return $this->redirect(array('action' => 'index'));
	}

	public function login() {
        if ($this->request->is('post')) {
        	#Login invalido
        	#Buscar usuario
        	$dataUser = $this->User->findByUsername($this->request->data['User']['username']);
        	if ($dataUser) {
        		#Revisa si usuario está bloqueado antes de logear
        		if ($dataUser['User']['state'] != 1) {
        			$this->Session->setFlash(__('Usuario bloqueado, favor contactar administrador.'));
        			return;
        		}

        		#Login de sistema
        		if ($this->Auth->login()) {
	            	#Login valido
	            	#Revisar caducida contraseña
					$date1 = strtotime($dataUser['User']['expire_pass']);
					$date2 = time();
					$subTime = $date1 - $date2;
					$d = ($subTime/(60*60*24))%365;
					if ($d <= 0) {
						$this->Session->setFlash(__('Su contraseña ha expirado, favor ingrese una nueva.'));
						$this->redirect(array('controller' => 'users', 'action' => 'changepassword', 'admin' => false));	
						return;
					}

					$name = $dataUser['User']['name'];
					$this->Session->setFlash(__('Bienvenido ' . $name . '!!!'));
	                $this->redirect(array('controller' => 'pages', 'action' => 'home', 'admin' => false));
	            } else {
	            	#Login invalido
	            	#Suma un intento
            		$this->User->id = $dataUser['User']['id'];
        			$attemp = $dataUser['User']['attemp'] + 1;
        			$updateData = array(
        					'attemp' => $attemp
        				);
        			$this->User->save($updateData);
        			$this->Session->setFlash(__('Usuario o contraseña inválido, favor intentar nuevamente.'));

            		#Contamos total de intentos, si es mayor 3 bloquea usuario
            		if ($dataUser['User']['attemp'] > 2) {
            			$this->User->id = $dataUser['User']['id'];
            			$updateData = array(
            					'state' => 0
            				);
            			$this->User->save($updateData);
            			$this->Session->setFlash(__('Usuario bloqueado, favor contactar administrador.'));
            		}
	            }
        	} else {
        		$this->Session->setFlash(__('Usuario inválido, favor contactar administrador.'));
        	}
        }
    }

    public function logout() {
        $this->Auth->logout();
		$this->redirect(array('controller' => 'users', 'action' => 'login', 'admin' => false));
    }

    public function changepassword() {
    	if ($this->request->is('post')) {
    		#Verificamos si las contraseñas ingrsadas son iguales
    		if ($this->request->data['User']['nueva_pass'] == $this->request->data['User']['nueva_pass_r']) {
    			#Extraemos data de usuario segun id almacenado en componente autorización
    			$dataUser = $this->User->findById($this->Auth->user('id'));

    			#Variabbles a comparar
    			$new_pass = AuthComponent::password($this->request->data['User']['nueva_pass']); 	#nueva contraseña(es cifrada)
    			$current_pass =  $dataUser['User']['password'];										#contraseña actual
    			$log_pass1 = $dataUser['User']['log_pass1'];										#contraseña anterior 1
    			$log_pass2 = $dataUser['User']['log_pass2'];										#contraseña anterior 2
    			$log_pass3 = $dataUser['User']['log_pass3'];										#contraseña anterior 3

    			#Compara contraseña nueva con las registradas, si alguna coincide no actualiza
    			if (
    				$new_pass == $current_pass ||
    				$new_pass == $log_pass1 ||
    				$new_pass == $log_pass2 ||
    				$new_pass == $log_pass3
    				) {
    				$this->Session->setFlash(__('Las contraseñas ingresada ya ha sido utilizada anteriormente, favor intentar nuevamente.'));
    			} else {
    			#Actualizamos los registros de contraseñas
    				#60 dias mas de expiracion
    				$fecha60mas = $final = date("Y-m-d H:i:s", strtotime("+2 month", time()));
    				$dataPass = array(
    								#Primero, pasamos log_pass2 a log_pass3, último log de contraseña(log_pass3) se "pierde"
    								'log_pass3' => $log_pass2,
    								#Segundo, pasamos log_pass1 a log_pass2
    								'log_pass2' => $log_pass1,
    								#Tercero, pasamos current_pass(contraseña actual) a los_pass1
    								'log_pass1' => $current_pass,
    								#Cuarto, actualizamos la actual contraseña con la nueva
    								'password' => $new_pass,
    								#Quinto, agregamos 60 dias mas al periodo de caducida
    								'expire_pass' => $fecha60mas

    							);
    				$this->User->id = $this->Auth->user('id');
	    			if ($this->User->save($dataPass, false)) {
						$this->Session->setFlash(__('Contraseña actualizada correctamente..'));
						$this->Auth->logout();
	        			$this->redirect(array('controller' => 'users', 'action' => 'login', 'admin' => false));
					} else {
						$this->Session->setFlash(__('La contraseña no ha sido actualizada, favor intentar nuevamente.'));
					}
    			}
    		} else {
    			$this->Session->setFlash(__('Las contraseñas no coinciden, favor intentar nuevamente.'));
    		}
    	}
	}
}

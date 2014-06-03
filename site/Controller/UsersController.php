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
            'logoutRedirect' => "",
            'authorize' => array('Controller')
        )
    );

	public function beforeFilter(){
        parent::beforeFilter();
        $this->Auth->allow('admin_index', 'admin_view', 'admin_edit', 'admin_add', 'admin_delete', 'changepassword');
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
					$this->Session->setFlash(__("Bienvenido $name!!!"));
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
        $this->Session->destroy();
        $this->redirect(array('controller' => 'users', 'action' => 'login', 'admin' => false));
    }

    public function changepassword() {
    	if ($this->request->is('post')) {
    		if ($this->request->data['User']['nueva_pass'] == $this->request->data['User']['nueva_pass_r']) {
    			$this->User->id = $this->Auth->user('id');
				$dataPass = array('password' => AuthComponent::password($this->request->data['User']['nueva_pass']), 'first_login' => 0);
				if ($this->User->save($dataPass, false)) {
					$this->Session->setFlash(__('Contraseña actualizada correctamente..'));
					$this->Auth->logout();
        			$this->redirect(array('controller' => 'users', 'action' => 'login', 'admin' => true));
				} else {
					$this->Session->setFlash(__('La contraseña no ha sido actualizada, favor intentar nuevamente.'));
				}
				
    		} else {
    			$this->Session->setFlash(__('Las contraseñas no coinciden, favor intentar nuevamente.'));
    		}
    	}
	}
}

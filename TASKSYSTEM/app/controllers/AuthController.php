<?php
require_once __DIR__ . '/../Core/Controller.php';

class AuthController extends Controller{
    
    private User $userModel;

    public function __construct()
    {
        $this->userModel = $this->model('User');
    }

    public function register(){
        if($_SERVER['REQUEST_METHOD']!== 'POST'){
            $this->redirect('/register');
        }

        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';
        $name = $_POST['name'] ?? '';
        $email = $_POST['email'] ?? '';
        
        $this->userModel->create([
        'username' => $username,
        'password' => $password,
        'name' => $name,
        'email' => $email,
        'active' => 1,
        'created_at' => date('Y-n-d H:i:s')
        ]);
        
        $this->redirect('/login');


    }
}
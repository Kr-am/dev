<?php
require_once __DIR__ . '/../Core/Controller.php';

class PublicController extends Controller
{
    public function __construct()
    {
    }

    public function index(){
        $this->view('auth/login');
    }
    public function login(){
        $this->view('auth/lgoin');
    }
    public function register(){
        $this->view('auth/registration');
    }
}
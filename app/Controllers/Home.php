<?php

namespace App\Controllers;

class Home extends BaseController
{
    public function index(): string
    {
        return view('Login/login');
    }

    public function login()
    {
        return redirect()->to(site_url('admin'));
    }

    public function admin(): string
    {
        return view('Dashboard/admin');

    }
}

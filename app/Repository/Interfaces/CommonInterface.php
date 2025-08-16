<?php

namespace App\Repository\Interfaces;

use Illuminate\Http\Request;

interface CommonInterface{
    public function index($page,$search);
    public function store($request);
    public function findById($id);
    public function delete($id);
    
}
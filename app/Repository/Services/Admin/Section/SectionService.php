<?php

namespace App\Repository\Services\Admin\Section;

use Illuminate\Support\Facades\DB;

class SectionService
{
    public function paginate(int $perPage, int $page, string $search) { return DB::table('sections')->where('name', 'like', "%{$search}%")->orderBy('display_order')->orderBy('name')->paginate($perPage, ['id', 'name', 'display_order', 'is_active', 'created_at', 'updated_at'], 'page', $page); }
    public function find(int $id) { return DB::table('sections')->where('id', $id)->first(['id', 'name', 'display_order', 'is_active', 'created_at', 'updated_at']); }
    public function create(array $data) { $id = DB::table('sections')->insertGetId($data); return $this->find($id); }
    public function update(int $id, array $data) { DB::table('sections')->where('id', $id)->update($data); return $this->find($id); }
    public function delete(int $id) { return DB::table('sections')->where('id', $id)->delete(); }
}

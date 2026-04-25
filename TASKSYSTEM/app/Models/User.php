<?php
Class User extends Model
{
    protected $table = 'tbl_user';
    
    public function create(array $data): bool
    {
        if (isset($data['password'])){
            $data['password'] = password_hash($data['password'],
            PASSWORD_DEFAULT);
        }
        return $this->insert($data);
    }
}
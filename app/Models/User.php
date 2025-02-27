<?php


namespace App\Models;


use App\Core\Database\Connection;
use React\Promise\PromiseInterface;

class User extends Model
{
    public static string $username = '';
    public static string $email = '';
    public static string $token = '';
    public static string $type = '';
    public string $table = 'user';
    public int $id;

    public static function getToken()
    {

    }

    public static function setToken(int $userId, string $token): PromiseInterface
    {
        self::$token = $token;
        return Connection::get()
            ->query('UPDATE users SET token = ? WHERE id = ?', [$token, $userId]);
    }
}
<?php
// api/utils/Response.php

class Response
{
    public static function send($success, $data = [], $message = '', $statusCode = 200)
    {
        http_response_code($statusCode);
        echo json_encode([
            'success' => $success,
            'message' => $message,
            'data' => $data
        ]);
        exit;
    }

    public static function error($message, $statusCode = 400)
    {
        self::send(false, [], $message, $statusCode);
    }

    public static function success($data = [], $message = 'Success')
    {
        self::send(true, $data, $message, 200);
    }
}
?>
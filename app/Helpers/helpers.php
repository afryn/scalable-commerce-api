<?php
if (!function_exists('error')) {

    function error(string $message, int $statusCode = 400)
    {
        return response()->json([
            'success' => false,
            'message' => $message,
        ], $statusCode);
    }
}
if (!function_exists('success')) {

    function success(string $message, $data, $statusCode = 200)
    {
        return response()->json([
            'message' => $message,
            'data' => $data,
        ], $statusCode);
    }
}
if (!function_exists('prx')) {

    function prx($data)
    {
        print_r($data);
        die;
    }
}
if(!function_exists('exception')){
    function exception($message, $code){
         throw new \Illuminate\Http\Exceptions\HttpResponseException(
        response()->json([
            'message' => $message
        ], $code)
    );
    }
}

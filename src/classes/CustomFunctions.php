<?php
namespace EndrockTheme\Classes;

use Timber\Timber;

class CustomFunctions
{

    public function __construct()
    {
    }

    public function getField(String $name, Int $id)
    {
        return get_field($name, $id, true);
    }

    public function setPost(Int $postId){
        return Timber::get_post($postId, 'Timber\Post');
    }

}

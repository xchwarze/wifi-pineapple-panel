<?php namespace pineapple;

abstract class SystemModule extends Module
{
    protected function changePassword($current, $new)
    {
        $shadow_file = file_get_contents('/etc/shadow');
        $root_array = explode(":", explode("\n", $shadow_file)[0]);
        $salt = '$1$'.explode('$', $root_array[1])[2].'$';
        $current_shadow_pass = $salt.explode('$', $root_array[1])[3];
        $current = crypt($current, $salt);
        $new = crypt($new, $salt);
        if ($current_shadow_pass == $current) {
            $find = implode(":", $root_array);
            $root_array[1] = $new;
            $replace = implode(":", $root_array);

            $shadow_file = str_replace($find, $replace, $shadow_file);
            file_put_contents("/etc/shadow", $shadow_file);

            return true;
        }
        return false;
    }
}

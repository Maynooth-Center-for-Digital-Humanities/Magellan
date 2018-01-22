<?php
/**
 * Created by PhpStorm.
 * User: fabianopallonetto
 * Date: 18/01/2018
 * Time: 08:29
 */

namespace App\EntryFormats;

class Factory
{
    public static $path_to_provider = "App\EntryFormats\Provider";

    public static function create($json,$locale='')
    {

        $provider_name=json_decode($json)->type;

        $provider = self::findProviderClassname($provider_name,$locale);


        try {

            $provider_class = new $provider();

        } catch (Exception $e) { sprintf('"%s" - Unable to find provider "%s"', $e, $provider); };

        return $provider_class;
    }

    protected static function findProviderClassname($provider, $locale = '')
    {
        $providerClass = self::$path_to_provider.($locale ? sprintf('\%s\%s', $locale, $provider) : sprintf('\%s', $provider))."\EntryFormat";

        if (class_exists($providerClass, true)) {
            return $providerClass;
        }
    }


}
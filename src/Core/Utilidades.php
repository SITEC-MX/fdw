<?php
/**
 * Sistemas Especializados e Innovación Tecnológica, SA de CV
 * Mpsoft.FDW - Framework de Desarrollo Web para PHP
 *
 * v.2.0.0.0 - 2021-01-26
 */
namespace Mpsoft\FDW\Core;

abstract class Utilidades
{
    public static function ObtenerIPCliente():string
    {
        $ip = NULL;

        if(isset($_SERVER["HTTP_CF_CONNECTING_IP"])) // Si es una conexión con Cloudflare
        {
            $ip = $_SERVER["HTTP_CF_CONNECTING_IP"];
        }
        else // Si no es una conexión con Cloudflare
        {
            $ip = isset($_SERVER["HTTP_X_REAL_IP"]) ? $_SERVER["HTTP_X_REAL_IP"] : $_SERVER['REMOTE_ADDR'];
        }

        return $ip;
    }
}
<?php
/**
 * Sistemas Especializados e Innovación Tecnológica, SA de CV
 * Mpsoft.FDW - Framework de Desarrollo Web para PHP
 *
 * v.2.0.0.0 - 2019-06-02
 */
namespace Mpsoft\FDW\Sesion;

use Mpsoft\FDW\Dato\BdD;
use Exception;
use \Mpsoft\FDW\Core\Utilidades;

class RateLimit
{
    private $bdd;

    private $ip;

    private $id;
    private $nombre;
    private $vigencia;
    private $maximo;

    private $tiempo_bloqueo;

    public function __construct(BdD $bdd, string $nombre, int $limite_de_tiempo, int $maximo, int $tiempo_bloqueo)
    {
        if(!$nombre) { throw new Exception("No se especificó el nombre del rate-limit."); }
        if(!$limite_de_tiempo || $limite_de_tiempo < 0) { throw new Exception("El tiempo límite en segundos no es válido."); }
        if(!$maximo || $maximo < 1) { throw new Exception("El máximo permitido no es válido."); }
        if(!$tiempo_bloqueo || $tiempo_bloqueo < 1) { throw new Exception("El tiempo de bloqueo no es válido."); }

        $this->bdd = $bdd;

        $this->ip = Utilidades::ObtenerIPCliente();
        $this->nombre = $nombre;
        $this->tiempo_bloqueo = $tiempo_bloqueo;

        // Cargamos los rate-limits del usuario
        $this->CargarRateLimits();

        $tiempo = time();

        if( isset(RateLimit::$ratelimits[$nombre]) ) // Si el rate-limit ya está definido
        {
            $rl = RateLimit::$ratelimits[$nombre];

            $this->id = $rl["id"];

            if($rl["vigencia"] >= $tiempo) // Si el RL sigue vigente
            {
                $this->vigencia = $rl["vigencia"];
                $this->maximo = $rl["restante"];
            }
            else // Si el RL ya expiró
            {
                $this->vigencia = $tiempo + $limite_de_tiempo;
                $this->maximo = $maximo;
            }
        }
        else // Si el rate-limit no está creado
        {
            $this->id = 0;
            $this->vigencia = $tiempo + $limite_de_tiempo;
            $this->maximo = $maximo;
        }
    }

    public function Permitir()
    {
        return $this->maximo > 0;
    }

    public function Consumir()
    {
        // Descontamos uno
        $this->maximo --;

        if($this->maximo <= 0) // Si el RateLimit se ha bloqueado
        {
            $this->vigencia += $this->tiempo_bloqueo;
        }

        $this->ActualizarRateLimit();
    }

    private function ActualizarRateLimit()
    {
        if($this->id) // Si el RL ya existe
        {
            $this->bdd->EjecutarUPDATE
                (
                    "ratelimit", // Tabla
                    array("nombre", "vigencia", "restante"), // Campos
                    array($this->nombre, $this->vigencia, $this->maximo), // Valores
                    array // Filtros
                    (
                        "id" => array( array("operador"=>FDW_DATO_BDD_OPERADOR_IGUAL, "operando"=>$this->id) )
                    )
                );
        }
        else // Si es un RL nuevo
        {
            $this->bdd->EjecutarINSERT
                (
                    "ratelimit", // Tabla
                    array("nombre", "ip", "vigencia", "restante"), // Campos
                    array($this->nombre, $this->ip, $this->vigencia, $this->maximo) // Valores
                );
        }
    }


    private static $ratelimits = NULL;
    private function CargarRateLimits()
    {
        $query = $this->bdd->EjecutarSELECT
            (
                "ratelimit", // Tabla
                array("id", "nombre", "vigencia", "restante"), // Campos
                array // Filtros
                (
                    "ip" => array( array("operador"=>FDW_DATO_BDD_OPERADOR_IGUAL, "operando"=>$this->ip) )
                )
            );

        $ratelimits = array();

        while($rl = $query->ObtenerSiguienteResultado(TRUE)) // Para cada rate limit obtenido
        {
            $nombre = $rl["nombre"];
            unset($rl["nombre"]);

            $ratelimits[$nombre] = $rl;
        }

        RateLimit::$ratelimits = $ratelimits;
    }
}

<?php
/**
 * Sistemas Especializados e Innovación Tecnológica, SA de CV
 * Mpsoft.FDW - Framework de Desarrollo Web para PHP
 *
 * v.2.0.0.0 - 2020-07-09
 */
namespace Mpsoft\FDW\Sesion;

use \Mpsoft\FDW\Core\ManejadorDeEventos;
use \Mpsoft\FDW\Sesion\Usuario;
use \Mpsoft\FDW\Core\Utilidades;

abstract class Sesion
{
    private $manejador_de_eventos = NULL;


    public function __construct()
    {
        $this->manejador_de_eventos = new ManejadorDeEventos( array("sesion_iniciada_correctamente", "sesion_reiniciada_correctamente", "sesion_cerrada_correctamente", "antes_de_cerrar_sesion") );
    }

    public function IniciarSesion(Usuario $usuario):?Token
    {
        $token = NULL;

        if( $this->UsuarioPuedeIniciarSesion($usuario) ) // Si el usuario puede iniciar sesión
        {
            $this->usuario = $usuario;

            $this->token = $this->CrearToken();

            $this->manejador_de_eventos->DispararEvento("sesion_iniciada_correctamente");
        }

        return $token;
    }

    public function ReiniciarSesion(string $token_str):bool
    {
        $sesion_reiniciada = FALSE;

        $token = $this->InicializarToken($token_str);

        if($token) // Si el token se inicia correctamente
        {
            if($this->ValidarTokenReiniciado($token)) // Si el token reiniciado sigue siendo válido
            {
                $this->token = $token;

                $this->usuario = $token->ObtenerUsuario();

                $sesion_reiniciada = TRUE;

                $this->manejador_de_eventos->DispararEvento("sesion_reiniciada_correctamente");
            }
            else // Si el token reiniciado dejó de ser válido
            {
                $token->Eliminar();
            }
        }

        return $sesion_reiniciada;
    }

    public function CerrarSesion():void
    {
        if($this->SesionIniciada()) // Si la sesión está iniciada
        {
            $this->manejador_de_eventos->DispararEvento("antes_de_cerrar_sesion");

            $this->token->Eliminar();

            $this->manejador_de_eventos->DispararEvento("sesion_cerrada_correctamente");
        }
        else // Si la sesión no está iniciada
        {
            throw new Exception("La sesión no está iniciada.");
        }
    }

    public function SesionIniciada():bool
    {
        return $this->token !== NULL;
    }




    protected $token = NULL;

    protected abstract function CrearToken():Token;

    protected abstract function InicializarToken(string $token_str):?Token;

    protected function ValidarTokenReiniciado(Token $token):bool
    {
        $token_valido = TRUE;

        // Si la dirección IP registrada cambia el token deja de ser válido
        $ip_cliente = Utilidades::ObtenerIPCliente();
        $ip_token = $token->ObtenerValor("ip");
        if($ip_token != $ip_cliente) // Si la dirección IP del token cambió
        {
            $token_valido = FALSE;
        }

        // Si el user-agent registrado cambia el token deja de ser válido
        $HTTP_USER_AGENT = $_SERVER['HTTP_USER_AGENT'];
        $ua = $token->ObtenerValor("ua");
        if($HTTP_USER_AGENT != $ua) // Si el UA del token cambió
        {
            $token_valido = FALSE;
        }

        return $token_valido;
    }

    public function ObtenerToken():?string
    {
        $token_str = NULL;

        if($this->token) // Si hay token
        {
            $token_str = $this->token->ObtenerValor("token");
        }

        return $token_str;
    }




    protected $usuario = NULL;

    protected abstract function UsuarioPuedeIniciarSesion(Usuario $usuario):bool;

    public function ObtenerUsuario():?Usuario
    {
        return $this->usuario;
    }




    public function VincularEvento(string $evento_nombre, string $identificador, callable $callback, int $prioridad=10):void
    {
        $this->manejador_de_eventos->VincularEvento($evento_nombre, $identificador, $callback, $prioridad);
    }

    public function DesvincularEvento(string $evento_nombre, string $identificador):void
    {
        $this->manejador_de_eventos->DesvincularEvento($evento_nombre, $identificador);
    }

    protected function DispararEvento(string $evento_nombre):void
    {
        $this->manejador_de_eventos->DispararEvento($evento_nombre);
    }
}
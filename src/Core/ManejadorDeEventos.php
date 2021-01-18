<?php
/**
 * Sistemas Especializados e Innovaci�n Tecnol�gica, SA de CV
 * Mpsoft.FDW - Framework de Desarrollo Web para PHP
 *
 * v.2.0.0.0 - 2020-07-19
 */
namespace Mpsoft\FDW\Core;

use Exception;

class ManejadorDeEventos
{
    private $eventos = array();

    public function __construct(array $eventos_disponibles)
    {
        $this->InicializarEventos($eventos_disponibles);
    }

    private function InicializarEventos(array $eventos_a_inicializar):void
    {
        foreach($eventos_a_inicializar as $evento_nombre) // Para cada evento a inicializar
        {
            if(!is_string($evento_nombre)) // Si el nombre del evento no es string
            {
                throw new Exception("El nombre del evento debe ser una cadena.");
            }

            if(!isset($this->eventos[$evento_nombre])) // Si el evento no est� definido
            {
                $this->eventos[$evento_nombre] = array();
            }
            else // Si el vento ya est� definido
            {
                throw new Exception("Se intent� inicializar un evento existente.");
            }
        }
    }

    public function AgregarEvento(string $evento_a_inicializar):void
    {
        $this->InicializarEventos(array($evento_a_inicializar));
    }

    public function AgregarEventos(array $eventos_a_inicializar):void
    {
        $this->InicializarEventos($eventos_a_inicializar);
    }

    public function VincularEvento(string $evento_nombre, string $identificador, callable $callback, int $prioridad=10):void
    {
        if( isset($this->eventos[$evento_nombre]) ) // Si el evento est� definido
        {
            if(!isset($this->eventos[$evento_nombre][$identificador])) // Si el identificador est� disponible
            {
                $this->eventos[$evento_nombre][$identificador] = $callback;
            }
            else // Si el identificador ya fue asignado
            {
                throw new Exception("El identificador '{$identificador}' no est� disponible.");
            }
        }
        else // Si el evento no est� definido
        {
            throw new Exception("El evento '{$evento_nombre}' no est� definido.");
        }
    }

    public function DesvincularEvento(string $evento_nombre, string $identificador):void
    {
        if( isset($this->eventos[$evento_nombre]) ) // Si el evento est� definido
        {
            if(isset($this->eventos[$evento_nombre][$identificador])) // Si el identificador existe
            {
                unset($this->eventos[$evento_nombre][$identificador]);
            }
            else // Si el identificador no existe
            {
                throw new Exception("El identificador '{$identificador}' no est� definido.");

            }
        }
        else // Si el evento no est� definido
        {
            throw new Exception("El evento '{$evento_nombre}' no est� definido.");
        }
    }

    public function DispararEvento(string $evento_nombre):void
    {
        if( isset($this->eventos[$evento_nombre]) ) // Si el evento est� configurado
        {
            foreach($this->eventos[$evento_nombre] as $callback) // Para cada evento configurado
            {
                $callback();
            }
        }
        else // Si el evento no est� configurado
        {
            throw new Exception("El evento '{$evento_nombre}' no est� definido.");
        }
    }
}

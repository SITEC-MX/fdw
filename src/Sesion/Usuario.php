<?php
/**
 * Sistemas Especializados e Innovación Tecnológica, SA de CV
 * Mpsoft.FDW - Framework de Desarrollo Web para PHP
 *
 * v.1.0.0.0 - 2020-08-08
 */
namespace Mpsoft\FDW\Sesion;

abstract class Usuario extends \Mpsoft\FDW\Dato\ElementoSoloLectura
{
    /*
     * Obtiene el campo del Elemento que no es válido.
     * @return array Arreglo con la información del campo que no es válido. array( "campo" => campo, "error" => descripción del error ) o null si todos los campos son válidos.
     */
    public function ValidarDatos():?array
    {
        return NULL;
    }

    /**
     * Retorna un arreglo asociativo cuyo índice es el "nombreSQL" del campo (nombre del campo en la tabla del Elemento) y apunta a un arreglo asociativos con la información del campo del Elemento
     * @return array Arreglo asociativo de arreglos asociativos con los siguientes índices:
     * "requerido"               (Opcional) true si el campo es requerido,
     * "soloDeLectura"           (Opcional) true si campo de es de solo lectura,
     * "nombre"			        (Opcional) nombre del campo para el usuario. Si no se especifica será igual al nombreSQL
     * "tipoDeDato"              (Opcional) tipo de dato del campo, se asume FDW_DATO_STRING si no se especifica.
     * "tamanoMaximo"		    (Opcional) si no es especifica se asume 0 (ilimitado) tamaño máximo del campo (aplica sólo para el tipo de datos FDW_DATO_STRING)
     *
     * "ignorarAlAgregar"	    (Opcional) si se especifica en true el campo no se incluirá al agregar el Elemento.
     * "ignorarAlModificar"      (Opcional) si se especifica en true el campo no se incluirá al modificar el Elemento.
     * "ignorarAlObtenerValores" (Opcional) si se especifica en true el campo no se incluirá al obtener los valores del Elemento.
     *
     *  Ejemplo "id" => array("requerido" => true, "soloDeLectura" => true, "nombre" => "ID", "tipoDeDato" => FDW_DATO_INT)
     *
     * NOTA: El Elemento debe contener el campo "id"
     */
    public static function ObtenerInformacionDeCampos():array
    {
        $campos = array();

        $campos["id"] = array("requerido" => true, "soloDeLectura" => true, "nombre" => "ID", "tipoDeDato" => FDW_DATO_INT, "ignorarAlAgregar"=>true, "ignorarAlModificar"=>true);

        $campos["usuario"] = array("requerido" => true, "soloDeLectura" => false, "nombre" => "Usuario", "tipoDeDato" => FDW_DATO_STRING, "tamanoMaximo"=>255);
        $campos["contrasena"] = array("requerido" => true, "soloDeLectura" => true, "nombre" => "Contraseña", "tipoDeDato" => FDW_DATO_STRING, "tamanoMaximo"=>255, "ignorarAlObtenerValores"=>true, "ignorarAlModificar" => true);

        return $campos;
    }
}
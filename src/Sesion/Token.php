<?php
/**
 * Sistemas Especializados e Innovación Tecnológica, SA de CV
 * Mpsoft.FDW - Framework de Desarrollo Web para PHP
 *
 * v.2.0.0.0 - 2021-01-14
 */
namespace Mpsoft\FDW\Sesion;

use \Mpsoft\FDW\Core\Utilidades;

abstract class Token extends \Mpsoft\FDW\Dato\Elemento
{
    /**
     * Constructor del Elemento
     * @param ?int $id ID del Elemento o NULL para inicializar un Elemento nuevo.
     */
    public function __construct(?int $id = NULL)
    {
        parent::__construct($id);

        if($id) // Si el Elemento existe
        {
        }
        else // Si el Elemento es nuevo
        {
            $token = $this->GenerarTokenAleatorio();
            $this->AsignarValorSinValidacion("token", $token);

            $ip = Utilidades::ObtenerIPCliente();
            $this->AsignarValorSinValidacion("ip", $ip);

            $HTTP_USER_AGENT = $_SERVER['HTTP_USER_AGENT'];
            $this->AsignarValorSinValidacion("ua", $HTTP_USER_AGENT);

            $tiempo = time();
            $this->AsignarValorSinValidacion("creacion_tiempo", $tiempo);

            $vigencia_hasta = $tiempo + $this->ObtenerSegundosDeVigenciaToken();
            $this->AsignarValorSinValidacion("validohasta_tiempo", $vigencia_hasta);
        }
    }

    /*
     * Obtiene el campo del Elemento que no es válido.
     * @return array Arreglo con la información del campo que no es válido. array( "campo" => campo, "error" => descripción del error ) o null si todos los campos son válidos.
     */
    public function ValidarDatos():?array
    {
        return null;
    }







    private $usuario = NULL;
    public function AsignarUsuario(Usuario $usuario):void
    {
        \Mpsoft\FDW\Dato\Elemento::AsignarElemento($this, "usuario_id", $usuario);

        $this->usuario = $usuario;
    }

    private function CargarUsuario():void
    {
        $this->usuario = $this->InicializarUsuario($this->ObtenerValor("usuario_id"));
    }

    public function ObtenerUsuario():?Usuario
    {
        if(!$this->usuario){$this->CargarUsuario();}

        return $this->usuario;
    }

    protected abstract function InicializarUsuario(int $usuario_id):Usuario;









    protected abstract function GenerarTokenAleatorio():string;




    protected abstract function ObtenerSegundosDeVigenciaToken():int;







    /**
     * Obtiene el nombre de la tabla en la base de datos utilizada por el Elemento
     * @return string
     */
    public static function ObtenerNombreTabla():string
    {
        return "token";
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
        return array
        (
            "id" => array("requerido" => TRUE, "soloDeLectura" => TRUE, "nombre" => "ID", "tipoDeDato" => FDW_DATO_INT),
            "token" => array("requerido" => TRUE, "soloDeLectura" => TRUE, "nombre" => "Token", "tipoDeDato" => FDW_DATO_STRING, "tamanoMaximo"=>256, "ignorarAlObtenerValores"=>TRUE),
            "usuario_id" => array("requerido" => TRUE, "soloDeLectura" => TRUE, "nombre" => "ID del usuario", "tipoDeDato" => FDW_DATO_INT),
            "creacion_tiempo" => array("requerido" => TRUE, "soloDeLectura" => TRUE, "nombre" => "ID del usuario", "tipoDeDato" => FDW_DATO_INT),
            "validohasta_tiempo" => array("requerido" => TRUE, "soloDeLectura" => TRUE, "nombre" => "ID del usuario", "tipoDeDato" => FDW_DATO_INT),
            "ip" => array("requerido" => TRUE, "soloDeLectura" => TRUE, "nombre" => "ID del usuario", "tipoDeDato" => FDW_DATO_STRING, "tamanoMaximo"=>40),
            "ua" => array("requerido" => FALSE, "soloDeLectura" => TRUE, "nombre" => "User-Agent", "tipoDeDato" => FDW_DATO_STRING, "tamanoMaximo"=>1024)
        );
    }

    public static function ObtenerNombrePermiso():string
    {
        return "Token";
    }
}
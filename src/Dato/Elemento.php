<?php
/**
 * Sistemas Especializados e Innovación Tecnológica, SA de CV
 * Mpsoft.FDW - Framework de Desarrollo Web para PHP
 *
 * v.2.0.0.0 - 2016-10-12
 */
namespace Mpsoft\FDW\Dato;

use Mpsoft\FDW\Core\ManejadorDeEventos;

/**
 * Encapsula la información de un registro de una tabla en una base de datos y las operaciones que se pueden realizar sobre éste.
 */
abstract class Elemento
{
    protected $clase_actual;
    private $base_de_datos;
    private $datos_obtenidos = FALSE;

    /**
     * Arreglo con el valor de los campos del registro
     * @var array Arreglo con el valor de los campos del registro
     */
    private $valores;

    /**
     * Arreglo con los valores originales del Elemento.
     * @var array
     */
    private $valores_originales;

    /**
     * Arreglo con la información de los campos de la tabla. Este campo es llenado por el método _ObtenerInformacionDeCampos()
     * @var array Arreglo con la información de los campos de la tabla (nombre del campo, campo requerido, campo de solo lectura, etc.)
     */
    private $informacionDeCampos;

    /**
     * Indica si el Elemento tiene cambios
     * @var boolean Es true si el Elemento tiene cambios, de lo contrario su valor es false.
     */
    private $hayCambios = false;



    // Manejo de eventos
    private $manejador_de_eventos = NULL;

    public function VincularEvento(string $evento_nombre, string $identificador, callable $callback, int $prioridad=10):void
    {
        $this->manejador_de_eventos->VincularEvento($evento_nombre, $identificador, $callback, $prioridad);
    }

    public function DesvincularEvento(string $evento_nombre, string $identificador):void
    {
        $this->manejador_de_eventos->DesvincularEvento($evento_nombre, $identificador);
    }

    protected function AgregarEvento(string $evento_nombre):void
    {
        $this->manejador_de_eventos->AgregarEvento($evento_nombre);
    }

    protected function AgregarEventos(array $eventos_a_inicializar):void
    {
        $this->manejador_de_eventos->AgregarEventos($eventos_a_inicializar);
    }

    protected function DispararEvento(string $evento_nombre):void
    {
        $this->manejador_de_eventos->DispararEvento($evento_nombre);
    }



    /**
     * Constructor del Elemento
     * @param ?int $id ID del Elemento o NULL para inicializar un Elemento nuevo.
     * @throws ElementoException
     */
    public function __construct(?int $id = NULL)
    {
        $this->clase_actual = get_class($this);

        $this->manejador_de_eventos = new ManejadorDeEventos(
            array(
                    "AntesDeAplicarCambios", "DespuesDeAplicarCambios",
                    "AntesDeObtener", "DespuesDeObtener",
                    "AntesDeModificar", "DespuesDeModificar",
                    "AntesDeEliminar", "DespuesDeEliminar",
                    "AntesDeAgregar", "DespuesDeAgregar"
                ));

        $this->GenerarCamposDelElemento();

        $this->base_de_datos = $this->_InicializarBaseDeDatos();

        if ($id > 0) // Si es un Elemento existente
        {
            if (!$this->PedirAutorizacion(FDW_DATO_PERMISO_OBTENER))
            {
                throw new ElementoException("No cuenta con privilegios para obtener los datos del Elemento '{$this->clase_actual}'.", $this->clase_actual, FDW_ELEMENTO_ERROR_INICIALIZAR);
            }

            $this->valores["id"] = $id;
        }
        else // Si es un Elemento nuevo
        {
            if (!$this->PedirAutorizacion(FDW_DATO_PERMISO_AGREGAR))
            {
                throw new ElementoException("No cuenta con permiso para agregar el Elemento '{$this->clase_actual}'.", $this->clase_actual, FDW_ELEMENTO_ERROR_INICIALIZAR);
            }

            $this->hayCambios = TRUE;
            $this->datos_obtenidos = TRUE;
        }
    }

    /**
     * Verifica si el Elemento existe o no en la base de datos.
     * @return boolean Retorna true si el Elemento es nuevo y no existe en la base de datos, de lo contrario retorna false.
     */
    public function EsNuevo():bool
    {
        return !$this->valores['id'];
    }

    /**
     * Verifica si el Elemento tiene cambios.
     * @return boolean Retorna true si el Elemento tiene cambios, de lo contrario retorna false.
     */
    public function HayCambios():bool
    {
        return $this->hayCambios;
    }

    /**
     * Forza el Elemento a tener cambios. Este método es útil cuando el Elemento tiene otros Elementos internos con cambios.
     */
    public function IndicarQueHayCambiosInternos()
    {
        $this->hayCambios = true;
    }

    /**
     * Verifica si el Elemento contiene el campo especificado.
     * @return boolean Retorna true si el Elemento contiene el campo especificado, de lo contrario retorna false.
     */
    public function TieneElCampo(string $nombreCampo):bool
    {
        return isset($this->informacionDeCampos[$nombreCampo]);
    }

    /**
     * Genera los arreglos de campos del Elemento
     * @return void
     */
    private function GenerarCamposDelElemento():void
    {
        $this->valores = array();
        $this->informacionDeCampos = $this->_ObtenerInformacionDeCampos();

        foreach ($this->informacionDeCampos as $nombreSQL => $informacionDeCampo)
        {
            if (!isset($informacionDeCampo["tipoDeDato"]))
            {
                $this->informacionDeCampos[$nombreSQL]["tipoDeDato"] = FDW_DATO_STRING;
            }

            if (($this->informacionDeCampos[$nombreSQL]["tipoDeDato"] == FDW_DATO_STRING || $this->informacionDeCampos[$nombreSQL]["tipoDeDato"] == FDW_DATO_TEXT) && !isset($informacionDeCampo["tamanoMaximo"]))
            {
                $this->informacionDeCampos[$nombreSQL]["tamanoMaximo"] = 0;
            }

            if (!isset($informacionDeCampo["nombre"]))
            {
                $this->informacionDeCampos[$nombreSQL]["nombre"] = $nombreSQL;
            }

            if (!isset($informacionDeCampo["soloDeLectura"]))
            {
                $this->informacionDeCampos[$nombreSQL]["soloDeLectura"] = false;
            }

            if (!isset($informacionDeCampo["requerido"]))
            {
                $this->informacionDeCampos[$nombreSQL]["requerido"] = false;
            }

            if (!isset($informacionDeCampo["ignorarAlAgregar"]))
            {
                $this->informacionDeCampos[$nombreSQL]["ignorarAlAgregar"] = false;
            }

            if (!isset($informacionDeCampo["ignorarAlModificar"]))
            {
                $this->informacionDeCampos[$nombreSQL]["ignorarAlModificar"] = false;
            }

            if (!isset($informacionDeCampo["ignorarAlObtenerValores"]))
            {
                $this->informacionDeCampos[$nombreSQL]["ignorarAlObtenerValores"] = false;
            }

            $this->informacionDeCampos[$nombreSQL]["hayCambios"] = false;

            $this->valores[$nombreSQL] = null;
        }
    }

    /**
     * Asigna un valor a un campo del Elemento
     * @param string $campo Nombre del campo al que se le asignará el valor.
     * @param mixed $valor Valor que se le asignará al campo.
     * @return void
     */
    public function AsignarValor(string $campo, $valor):void
    {
        if(!$this->datos_obtenidos) // Si los datos no se han obtenido
        {
            $this->ObtenerDatos();
        }

        if (isset($this->informacionDeCampos[$campo])) // Si el campo pertenece al Elemento
        {
            if (Elemento::ValorConCambios($this->valores[$campo], $valor)) // Si el valor ha cambiado
            {
                if (!$this->informacionDeCampos[$campo]["soloDeLectura"]) // Si el campo se puede modificar
                {
                    $this->AsignarValorAlCampo($campo, $valor);
                }
                else // Si el campo no se puede moficiar
                {
                    throw new ElementoException("Se intentó asignar un valor al campo sólo de lectura '{$campo}'.", $this->clase_actual, FDW_ELEMENTO_ERROR_ASIGNARVALOR);
                }
            }
        }
        else // Si el campo no pertenece al Elemento
        {
            throw new ElementoException("Se intentó asignar un valor al campo '{$campo}' que no existe en el Elemento.", $this->clase_actual, FDW_ELEMENTO_ERROR_ASIGNARVALOR);
        }
    }

    /**
     * Asigna un valor a un campo del Elemento (sin validación)
     * @param string $campo Nombre del campo al que se le asignará el valor.
     * @param mixed $valor Valor que se le asignará al campo.
     * @return void
     */
    protected function AsignarValorSinValidacion(string $campo, $valor):void
    {
        if(!$this->datos_obtenidos) // Si los datos no se han obtenido
        {
            $this->ObtenerDatos();
        }

        if (isset($this->informacionDeCampos[$campo])) // Si el campo pertenece al Elemento
        {
            $va = Elemento::ValorEsNulo($this->valores[$campo]);
            $nv = Elemento::ValorEsNulo($valor);

            if ($this->valores[$campo] != $valor || ($va && !$nv) || (!$va && $nv)) // Si el valor ha cambiado
            {
                $this->AsignarValorAlCampo($campo, $valor);
            }
        }
    }

    /**
     * Asigna un valor al campo sin notificar al Elemento del cambio. El uso de este método no está recomendado.
     * @param string $campo
     * @param mixed $valor
     */
    protected function AsignarSinValidarNiNotificar(string $campo, $valor):void
    {
        if(isset($this->informacionDeCampos[$campo])) // Si el campo existe
        {
            // Corregimos los tipos
            $valor = is_null($valor) ? NULL : BdD::ConvertirATipoDeDato($valor, $this->informacionDeCampos[$campo]["tipoDeDato"]);

            $this->valores[$campo] = $valor;
        }
    }

    /**
     * Asigna un valor al campo, se asume que el campo existe y que su valor ha cambiado
     * @param string $campo
     * @param mixed $valor
     */
    private function AsignarValorAlCampo(string $campo, $valor):void
    {
        // Corregimos los tipos
        $valor = is_null($valor) ? NULL : BdD::ConvertirATipoDeDato($valor, $this->informacionDeCampos[$campo]["tipoDeDato"]);

        $this->valores[$campo] = $valor;
        $this->informacionDeCampos[$campo]["hayCambios"] = true;
        $this->hayCambios = true;
    }

    /**
     * Obtiene el valor de un campo del Elemento
     * @param string $campo Nombre del campo del que se obtendrá el valor.
     * @return mixed Valor del campo del campo.
     */
    public function ObtenerValor(string $campo)
    {
        // El campo id es el único que tenemos sin necesidad de obtener datos
        if(!$this->datos_obtenidos && $campo != "id") // Si los datos no se han obtenido
        {
            $this->ObtenerDatos();
        }

        if (isset($this->informacionDeCampos[$campo])) // Si el campo pertenece al Elemento
        {
            return $this->valores[$campo];
        }
        else // Si el campo no pertenece al Elemento
        {
            throw new ElementoException("Se intentó obtener el valor del campo '{$campo}' que no pertenece al Elemento '{$this->clase_actual}'.", $this->clase_actual, FDW_ELEMENTO_ERROR_OBTENERVALOR);
        }
    }

    /**
     * Verifica si el valor proporcionado es nulo o no.
     * @param mixed $valor Valor a verificar
     * @return boolean Returna true si el valor es nulo, de lo contrario retorna false
     */
    public static function ValorEsNulo($valor):bool
    {
        $valorNulo = $valor === null;

        if (!$valorNulo)
        {
            if (is_string($valor))
            {
                $valorNulo = $valor == "";
            }

            if (is_int($valor))
            {
                $valorNulo = false;
            }
        }

        return $valorNulo;
    }

    /**
     * Obtiene el campo requerido del Elemento cuyo valor no se ha proporcionado.
     * @return string Nombre del campo requerido que no se ha proporcionado o null si todos los campos requeridos se han proporcionado.
     */
    public function ObtenerCampoRequeridoNoProporcionado():?string
    {
        $campo = null;

        foreach ($this->valores as $indice => $valor) // Para cada campo del Elemento
        {
            if ($indice == "id")
            {
                continue;
            }

            if ($this->informacionDeCampos[$indice]["requerido"] && Elemento::ValorEsNulo($valor)) // Si el valor no se ha proporcionado y es requerido
            {
                $campo = $indice;
                break;
            }
        }

        return $campo;
    }

    /*
     * Obtiene el campo del Elemento que no es válido.
     * @return array Arreglo con la información del campo que no es válido. array( "campo" => campo, "error" => descripción del error ) o null si todos los campos son válidos.
     */
    public abstract function ValidarDatos():?array;

    /**
     * Aplica los cambios realizados al Elemento
     * @return void
     */
    public function AplicarCambios():void
    {
        if ($this->HayCambios()) // Si el Elemento tiene cambios
        {
            $campoRequeridoNoProporcionado = $this->ObtenerCampoRequeridoNoProporcionado();
            if ($campoRequeridoNoProporcionado != null) // Si hay un campo requerido no proporcionado
            {
                throw new ElementoException("El campo '{$campoRequeridoNoProporcionado}' es requerido y su valor no ha sido proporcionado en el Elemento '{$this->clase_actual}'.", $this->clase_actual, FDW_ELEMENTO_ERROR_ELEMENTONOVALIDO);
            }

            $campoNoValido = $this->ValidarDatos();
            if ($campoNoValido != null) // Si hay un campo no válido
            {
                $campo = $campoNoValido["campo"];
                $error = $campoNoValido["error"];

                throw new ElementoException("El campo '{$campo}' no es válido en el Elemento '{$this->clase_actual}'. {$error}", $this->clase_actual, FDW_ELEMENTO_ERROR_ELEMENTONOVALIDO);
            }

            $this->DispararEvento("AntesDeAplicarCambios");

            if ($this->valores["id"] > 0) // Si es un Elemento existente
            {
                $this->Modificar();
            }
            else // Si es un Elemento nuevo
            {
                $this->Agregar();
            }


            foreach ($this->informacionDeCampos as $nombreSQL => $informacionDeCampo) // Para cada campo del Elemento
            {
                $this->informacionDeCampos[$nombreSQL]["hayCambios"] = false;
            }
            $this->hayCambios = false;

            $this->DispararEvento("DespuesDeAplicarCambios");
        }
    }

    private function ObtenerDatos():void
    {
        // Permiso para obtener comprobado en constructor

        $this->DispararEvento("AntesDeObtener");

        $tabla = $this->_ObtenerNombreTabla();
        $id = $this->valores["id"];

        $campos = array();
        foreach ($this->valores as $indice => $valor) // Para cada campo del Elemento
        {
            $campos[] = $indice;
        }

        $resultado = $this->base_de_datos->EjecutarSELECT($tabla, $campos, array("id"=>array(array("operador"=>FDW_DATO_BDD_OPERADOR_IGUAL, "operando"=>$id)) ));
        if (!$this->valores = $resultado->ObtenerSiguienteResultado())
        {
            throw new ElementoException("El Elemento '{$this->clase_actual}' con ID '{$id}' no existe en la base de datos.", $this->clase_actual, FDW_ELEMENTO_ERROR_OBTENER);
        }
        $resultado->LiberarResultado();

        // Componemos los tipos
        foreach($this->valores as $campo_nombre=>$campo_valor) // Para cada campo obtenido
        {
            // Si se obtiene un valor o si no se obtiene pero el campo es requerido
            if($campo_valor !== NULL || $this->informacionDeCampos[$campo_nombre]["requerido"])
            {
                $this->valores[$campo_nombre] = BdD::ConvertirATipoDeDato($this->valores[$campo_nombre], $this->informacionDeCampos[$campo_nombre]["tipoDeDato"]);
            }
        }

        $this->valores_originales = $this->valores;

        $this->datos_obtenidos = TRUE;

        $this->DispararEvento("DespuesDeObtener");
    }

    /**
     * Agrega el Elemento a la base de datos.
     */
    private function Agregar():void
    {
        // Permiso para agregar comprobado en constructor

        $this->DispararEvento("AntesDeAgregar");

        $this->_Agregar();

        $this->DispararEvento("DespuesDeAgregar");
    }

    /**
     * Procedimiento que agrega el Elemento. Este método será invocado por el Elemento al ejecutar el método Agregar.
     * _Agregar se ejecuta después de ejecutar el evento AntesDeAgregar y antes de ejecutar el evento DespuesDeAgregar
     */
    private function _Agregar():void
    {
        $tabla = $this->_ObtenerNombreTabla();

        $campos = array();
        $valores = array();

        foreach ($this->valores as $indice => $valor) // Para cada campo del Elemento
        {
            if ($indice == "id" || $this->informacionDeCampos[$indice]["ignorarAlAgregar"] || !$this->informacionDeCampos[$indice]["hayCambios"]) // Si el campo se debe ignorar
            {
                continue;
            }

            $campos[] = $indice;
            $valores[] = Elemento::ValorEsNulo($valor) ? NULL : $valor;
        }

        if ($id = $this->base_de_datos->EjecutarINSERT($tabla, $campos, $valores)) // Si el Elemento se agrega correctamente
        {
            $this->valores["id"] = $id;
        }
        else // Error al agregar el Elemento
        {
            throw new ElementoException("Error al agregar el Elemento '{$this->clase_actual}' en la base de datos. " . $this->base_de_datos->ObtenerUltimoError(), $this->clase_actual, FDW_ELEMENTO_ERROR_AGREGAR);
        }
    }

    /**
     * Modifica el Elemento de la base de datos.
     */
    private function Modificar():void
    {
        if (!$this->PedirAutorizacion(FDW_DATO_PERMISO_MODIFICAR))
        {
            throw new ElementoException("No cuenta con privilegios para modificar el Elemento '{$this->clase_actual}'.", $this->clase_actual, FDW_ELEMENTO_ERROR_MODIFICAR);
        }

        $this->DispararEvento("AntesDeModificar");

        if($this->HayCambios()) // Si hay cambios en los datos
        {
            $this->_Modificar();
        }

        $this->DispararEvento("DespuesDeModificar");
    }

    /**
     * Procedimiento que modificar el Elemento. Este método será invocado por el Elemento al ejecutar el método Modificar.
     * _Modificar se ejecuta después de ejecutar el evento AntesDeModificar y antes de ejecutar el evento DespuesDeModificar
     */
    private function _Modificar():void
    {
        $tabla = $this->_ObtenerNombreTabla();
        $id = $this->valores["id"];

        $campos = array();
        $valores = array();

        foreach ($this->valores as $indice => $valor) // Para cada campo del Elemento
        {
            if ($indice == "id" || $this->informacionDeCampos[$indice]["ignorarAlModificar"] || !$this->informacionDeCampos[$indice]["hayCambios"]) // Si el campo se debe ignorar
            {
                continue;
            }

            $campos[] = $indice;
            $valores[] = Elemento::ValorEsNulo($valor) ? NULL : $valor;
        }

        // Es posible que el Elemento tenga cambios pero que se deben ignorar al modificar
        if(count($campos)) // Si hay campos a modificar
        {
            if (!$this->base_de_datos->EjecutarUPDATE($tabla, $campos, $valores, array("id"=>array(array("operador"=>FDW_DATO_BDD_OPERADOR_IGUAL, "operando"=>$id)) ))) // Si ocurre algún error con la consulta
            {
                throw new ElementoException("Error al modificar el Elemento '{$this->clase_actual}' en la base de datos.", $this->clase_actual, FDW_ELEMENTO_ERROR_MODIFICAR);
            }
        }
    }

    /**
     * Elimina el Elemento.
     */
    public function Eliminar():void
    {
        if (!$this->PedirAutorizacion(FDW_DATO_PERMISO_ELIMINAR))
        {
            $elemento_id = $this->ObtenerValor("id");

            throw new ElementoException("No cuenta con privilegios para eliminar el Elemento '{$this->clase_actual}' con ID '{$elemento_id}'.", $this->clase_actual, FDW_ELEMENTO_ERROR_ELIMINAR);
        }

        $this->DispararEvento("AntesDeEliminar");

        $this->_Eliminar();

        $this->DispararEvento("DespuesDeEliminar");
    }

    /**
     * Procedimiento que elimina el Elemento. Este método será invocado por el Elemento al ejecutar el método Eliminar.
     * _Eliminar se ejecuta después de ejecutar el evento AntesDeEliminar y antes de ejecutar el evento DespuesDeEliminar
     */
    protected abstract function _Eliminar():void;

    /**
     * Verifica si se tiene permiso para realizar la acción sobre el Elemento
     * @param int $accion Acción a verificar
     * @return boolean
     */
    protected abstract function PedirAutorizacion(int $accion):bool;






    /**
     * Elimina un Elemento de la base de datos.
     * @param mixed $tabla
     * @param mixed $elemento_id
     * @param mixed $base_de_datos
     */
    public static function EliminarElemento(Elemento $elemento)
    {
        $base_de_datos = $elemento->_InicializarBaseDeDatos();

        $base_de_datos->EjecutarDELETE
            (
                $elemento->_ObtenerNombreTabla(), // Tabla
                array // Filtros
                (
                    "id" => array(array("operador"=>FDW_DATO_BDD_OPERADOR_IGUAL, "operando"=>$elemento->ObtenerValor("id")))
                )
            );
    }







    /**
     * Inicializa el gestor de base de datos utilizado por el Elemento
     * @return \Mpsoft\FDW\Dato\BdD
     */
    protected function _InicializarBaseDeDatos():BdD
    {
        return $this->clase_actual::InicializarBaseDeDatos();
    }

    /**
     * Obtiene el nombre de la tabla en la base de datos utilizada por el Elemento
     * @return string
     */
    protected function _ObtenerNombreTabla():string
    {
        return $this->clase_actual::ObtenerNombreTabla();
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
    protected function _ObtenerInformacionDeCampos():array
    {
        return $this->clase_actual::ObtenerInformacionDeCampos();
    }



    /**
     * Crea un nuevo Elemento, con la misma información pero diferente ID
     * @return Elemento
     */
    public function Clonar():Elemento
    {
        $nuevoElemento = new $this->clase_actual();

        $nuevoElemento->valores = $this->valores;

        foreach ($nuevoElemento->valores as $indice => $valor) // Para cada valor del nuevo Elemento
        {
            if (!Elemento::ValorEsNulo($valor)) // Si el valor no es nulo
            {
                $nuevoElemento->informacionDeCampos[$indice]["hayCambios"] = true;
            }
        }

        $nuevoElemento->AsignarValorSinValidacion('id', 0);

        return $nuevoElemento;
    }

    public function ObtenerValores():array
    {
        if(!$this->datos_obtenidos) // Si los datos no se han obtenido
        {
            $this->ObtenerDatos();
        }

        $valores = array();

        foreach ($this->valores as $indice => $valor) // Para cada campo del Elemento
        {
            if(!$this->informacionDeCampos[$indice]["ignorarAlObtenerValores"]) // Si el campo no se debe ignorar al recopilar los valores
            {
                $valores[$indice] = $valor;
            }
        }

        return $valores;
    }

    protected function ObtenerValoresConCambios():array
    {
        $valores = array();

        foreach ($this->informacionDeCampos as $nombreSQL => $informacionDeCampo) // Para cada campo en el Elemento
        {
            if($informacionDeCampo["hayCambios"]) // Si el campo tiene cambios
            {
                $valores[$nombreSQL] = $this->valores[$nombreSQL];
            }
        }

        return $valores;
    }

    protected function ObtenerValoresOriginalesModificados():array
    {
        $valores = array();

        foreach ($this->informacionDeCampos as $nombreSQL => $informacionDeCampo) // Para cada campo en el Elemento
        {
            if($informacionDeCampo["hayCambios"]) // Si el campo tiene cambios
            {
                $valores[$nombreSQL] = $this->valores_originales[$nombreSQL];
            }
        }

        return $valores;
    }

    public static function ValorConCambios($valor_actual, $nuevo_valor):bool
    {
        $va = Elemento::ValorEsNulo($valor_actual);
        $nv = Elemento::ValorEsNulo($nuevo_valor);

        return $valor_actual != $nuevo_valor || ($va && !$nv) || (!$va && $nv);
    }

    /**
     * Asigna el ID de un Elemento al campo que se indica de otro Elemento; validando que el Elemento a asignar sea del tipo correcto
     * @param Elemento $elemento_destino
     * @param mixed $campo_nombre
     * @param Elemento $elemento_a_asignar
     * @throws ElementoException
     */
    public static function AsignarElemento(Elemento $elemento_destino, string $campo_nombre, ?Elemento $elemento_a_asignar = NULL):void
    {
        if(isset($elemento_destino->informacionDeCampos[$campo_nombre])) // Si el campo existe en el elemento de destino
        {
            $elemento_a_asignar_id = NULL;

            if($elemento_a_asignar) // Si se proporciona el Elemento
            {
                if(!$elemento_a_asignar->EsNuevo()) // Si el Elemento existe en la base de datos
                {
                    $elemento_a_asignar_id = $elemento_a_asignar->ObtenerValor("id");
                }
                else // Si el Elemento es nuevo
                {
                    throw new ElementoException("El Elemento a asignar no puede ser nuevo.", get_class($elemento_destino), FDW_ELEMENTO_ERROR_ASIGNARVALOR);
                }
            }
            else // Si no se proporciona el elemento
            {
                if($elemento_destino->informacionDeCampos[$campo_nombre]["requerido"]) // Si el campo es requerido
                {
                    throw new ElementoException("El Elemento a asignar no puede ser nulo.", get_class($elemento_destino), FDW_ELEMENTO_ERROR_ASIGNARVALOR);
                }
            }

            $elemento_destino->AsignarValorSinValidacion($campo_nombre, $elemento_a_asignar_id);
        }
        else // Si el campo no existe en el elemento de destino
        {
            throw new ElementoException("El campo '{$campo_nombre}' no existe en el Elemento de destino.", get_class($elemento_destino), FDW_ELEMENTO_ERROR_ASIGNARVALOR);
        }
    }


    /**
     * Inicializa el gestor de base de datos utilizado por el Elemento
     * @return \Mpsoft\FDW\Dato\BdD
     */
    public abstract static function InicializarBaseDeDatos():BdD;

    /**
     * Obtiene el nombre de la tabla en la base de datos utilizada por el Elemento
     * @return string
     */
    public abstract static function ObtenerNombreTabla():string;

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
    public abstract static function ObtenerInformacionDeCampos():array;
}

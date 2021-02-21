<?php
/**
 * Sistemas Especializados e Innovación Tecnológica, SA de CV
 * Mpsoft.FDW - Framework de Desarrollo Web para PHP
 *
 * v.2.0.0.0 - 2020-09-13
 */

use \Mpsoft\FDW\Core\Parametro;

function FDW_GET_Modulo(array $OPENAPI_REQUEST, string $modulo_clase, ?array $filtro_base = NULL):array
{
    global $SESION;

    $estado = array("estado"=>NO_INICIALIZADO, "mensaje"=>"No inicializado", "http_response_code"=>NULL);

    $campos_disponibles = $modulo_clase::ObtenerInformacionDeCampos();
    $permiso_nombre = $modulo_clase::ObtenerNombrePermiso();

    $hay_error = FALSE;

    $_ = isset($OPENAPI_REQUEST["get"]["_"]) ? $OPENAPI_REQUEST["get"]["_"] : time();
    $inicio = $OPENAPI_REQUEST["get"]["inicio"] - 1; // Los registros inician en el 0
    $registros_por_pagina = $OPENAPI_REQUEST["get"]["registros"];

    $tiene_permiso_obtener = $SESION->PedirAutorizacion($permiso_nombre, FDW_DATO_PERMISO_OBTENER);
    if($tiene_permiso_obtener) // Si tiene el permiso para obtener los datos del módulo
    {
        if($inicio >= 0) // Si el registro inicial es válido
        {
            if($registros_por_pagina >= 1) // Si la cantidad de registros solicitada es válida
            {
                // Procesamos los campos solicitados
                $campos = NULL;
                if( isset($OPENAPI_REQUEST["get"]["campos"]) ) // Si se solicitan campos específicos
                {
                    $campos_solicitados = explode(",", $OPENAPI_REQUEST["get"]["campos"]);

                    $campos = array();
                    foreach($campos_solicitados as $campo) // Para cada campo solicitado
                    {
                        if( isset($campos_disponibles[$campo]) ) // Si el campo existe
                        {
                            $campos[] = $campo;
                        }
                        else // Si el campo no existe
                        {
                            $estado["estado"] = FDW_MODULO_CAMPO_INEXISTENTE;
                            $estado["mensaje"] = "El campo '{$campo}' no existe.";
                            $estado["campo"] = $campo;
                            $estado["http_response_code"] = 400;

                            $hay_error = TRUE;
                        }
                    }
                }
                else // Si no se solicitan campos específicos
                {
                    $campos = $modulo_clase::ObtenerCamposPredeterminados();
                }

                // Procesamos el ordenamiento
                $ordenamiento = NULL;
                if(!$hay_error) // Si no hay error en campos
                {
                    if( isset($OPENAPI_REQUEST["get"]["ordenamiento_campos"]) ) // Si se solicitan campos específicos
                    {
                        $ordenamientos_solicitados = explode(",", $OPENAPI_REQUEST["get"]["ordenamiento_campos"]);

                        $ordenamiento = array();
                        foreach($ordenamientos_solicitados as $campo) // Para cada campo solicitado
                        {
                            $campo_sin_signo = NULL;
                            $orden = "asc";

                            $posible_signo = $campo[0];
                            if( $posible_signo=='+' || $posible_signo=='-' ) // Si el campo tiene signo
                            {
                                $campo_sin_signo = substr($campo, 1);

                                if($posible_signo=='-') // Orden descendente
                                {
                                    $orden = "desc";
                                }
                            }
                            else // Si el campo no tiene signo
                            {
                                $campo_sin_signo = $campo;
                            }

                            if( isset($campos_disponibles[$campo_sin_signo]) ) // Si el campo existe
                            {
                                $ordenamiento[] = "{$campo_sin_signo} {$orden}";
                            }
                            else // Si el campo no existe
                            {
                                $estado["estado"] = FDW_MODULO_ORDENAMIENTO_INEXISTENTE;
                                $estado["mensaje"] = "El campo '{$campo}' no existe.";
                                $estado["campo"] = $campo;
                                $estado["http_response_code"] = 400;

                                $hay_error = TRUE;
                            }
                        }
                    }
                }

                // Procesamos el filtro de búsqueda
                $filtro_busqueda = NULL;
                if(!$hay_error) // Si no hay error en campos ni ordenamiento
                {
                    if( isset($OPENAPI_REQUEST["get"]["busqueda"]) ) // Si se solicita una búsqueda
                    {
                        $campos_busqueda = NULL;

                        if( isset($OPENAPI_REQUEST["get"]["busqueda_campos"]) ) // Si se solicitan campos específicos
                        {
                            $campos_solicitados = explode(",", $OPENAPI_REQUEST["get"]["busqueda_campos"]);

                            $campos_busqueda = array();
                            foreach($campos_solicitados as $campo) // Para cada campo solicitado
                            {
                                if( isset($campos_disponibles[$campo]) ) // Si el campo existe
                                {
                                    $campos_busqueda[] = $campo;
                                }
                                else // Si el campo no existe
                                {
                                    $estado["estado"] = FDW_MODULO_BUSQUEDA_INEXISTENTE;
                                    $estado["mensaje"] = "El campo '{$campo}' no existe.";
                                    $estado["campo"] = $campo;
                                    $estado["http_response_code"] = 400;

                                    $hay_error = TRUE;
                                }
                            }
                        }
                        else // Si no se solicitan los campos específicos
                        {
                            $campos_busqueda = $campos;
                        }

                        if(!$hay_error) // Si no hay error en los campos de búsqueda
                        {
                            $busqueda = $OPENAPI_REQUEST["get"]["busqueda"];
                            $filtro_busqueda = array();

                            foreach($campos_busqueda as $campo) // Para cada campo de búsqueda
                            {
                                $campo_info = $campos_disponibles[$campo];
                                $tipoDeDato = $campo_info["tipoDeDato"];

                                $operador = NULL;
                                $operando = NULL;

                                switch($tipoDeDato)
                                {
                                    case FDW_DATO_STRING:
                                        $operador = FDW_DATO_BDD_OPERADOR_LIKE;
                                        $operando = "%{$busqueda}%";
                                        break;

                                    default:
                                        $operador = FDW_DATO_BDD_OPERADOR_IGUAL;
                                        $operando = $busqueda;
                                }

                                $filtro_busqueda[$campo] = array(array("tipo"=>FDW_DATO_BDD_LOGICA_O, "operador"=>$operador, "operando"=>$operando));
                            }
                        }
                    }
                }

                // Porcesamos los filtros
                $filtro_query = NULL;
                if(!$hay_error) // Si no hay error en campos ni ordenamiento
                {
                    if( isset($OPENAPI_REQUEST["get"]["filtro"]) ) // Si se proporciona un filtro
                    {
                        $filtro_query = array();

                        foreach($OPENAPI_REQUEST["get"]["filtro"] as $filtro) // Para cada filtro
                        {
                            $filtro_campo = $filtro["nombre"];
                            $filtro_operador = $filtro["operador"];
                            $filtro_valor = $filtro["valor"];
                            $filtro_concatenador = isset($filtro["concatenador"]) ? $filtro["concatenador"] : FDW_DATO_BDD_LOGICA_Y;

                            // Verificamos si el campo existe en el módulo
                            if( isset($campos_disponibles[$filtro_campo]) ) // Si el campo existe en el módulo
                            {
                                $filtro_enviado = array("operador"=>$filtro_operador, "operando"=>$filtro_valor, "tipo"=>$filtro_concatenador);

                                if( isset($filtro_query[$filtro_campo]) ) // Si el filtro ya existe
                                {
                                    $filtro_query[$filtro_campo][] = array($filtro_enviado);
                                }
                                else // Si el filtro es nuevo
                                {
                                    $filtro_query[$filtro_campo] = array($filtro_enviado);
                                }
                            }
                            else // Si el campo no existe en el módulo
                            {
                                throw new Exception("Filtro de grupos aún no implementado.");
                            }
                        }
                    }
                }


                if(!$hay_error) // Si no hay error en campos ni ordenamiento ni en filtros
                {
                    $filtros_conteo = isset($filtro_base) ? $filtro_base : array();
                    $filtros_datos = array();

                    if($filtro_base) // Si hay filtro base
                    {
                        $filtros_datos["grupo-base"] = array("filtros"=>$filtro_base);
                    }

                    if($filtro_busqueda) // Si hay filtro de búsqueda
                    {
                        $filtros_datos["grupo-busqueda"] = array("filtros"=>$filtro_busqueda);
                    }

                    if($filtro_query) // Si hay filtro query
                    {
                        $filtros_datos["grupo-query"] = array("filtros"=>$filtro_query);
                    }

                    // Query de resultados
                    $modulo = new $modulo_clase
                        (
                            $campos,
                            $filtros_datos,
                            $ordenamiento,
                            $inicio,
                            $registros_por_pagina
                        );

                    $registros = array();
                    while($elemento = $modulo->ObtenerSiguienteRegistro()) // Mientras haya registros
                    {
                        $registros[] = $elemento;
                    }

                    // Quiery de conteo filtrados
                    $filtrados = $modulo->ObtenerConteo();

                    // Query de conteo total
                    $modulo_conteo = new $modulo_clase
                        (
                            $campos,
                            $filtros_conteo
                        );

                    $total = $modulo_conteo->ObtenerConteo();

                    $resultado = array
                    (
                        "_" => $_,
                        "total" => $total,
                        "filtrados" => $filtrados,
                        "registros" => $registros
                    );

                    $estado["estado"] = OK;
                    $estado["mensaje"] = "OK";
                    $estado["resultado"] = $resultado;
                }
            }
            else // Si la cantidad de registros solicitada no es válida
            {
                $estado["estado"] = FDW_MODULO_REGISTROS_INVALIDO;
                $estado["mensaje"] = "La cantidad de registros solicitados debe ser mayor o igual a 1";
                $estado["http_response_code"] = 400;
            }
        }
        else // Si el registro inicial no es válido
        {
            $estado["estado"] = FDW_MODULO_INICIO_INVALIDO;
            $estado["mensaje"] = "El registro inicial debe ser mayor o igual a 1";
            $estado["http_response_code"] = 400;
        }
    }
    else // Si no tiene el permiso para obtener los datos del módulo
    {
        $estado["estado"] = SIN_PRIVILEGIOS;
        $estado["mensaje"] = utf8_encode("Sin permisos para obtener los datos del módulo.");
        $estado["http_response_code"] = 403;
    }

    return $estado;
}

function FDW_GET_Elemento(string $elemento_clase, ?int $elemento_id = NULL, ?callable $preparar_elemento_inicializado = NULL, ?callable $obtener_resultado=NULL):array
{
    global $SESION;

    $estado = array("estado"=>NO_INICIALIZADO, "mensaje"=>"No inicializado", "http_response_code"=>NULL);

    $permiso_nombre = $elemento_clase::ObtenerNombrePermiso();

    $tiene_permiso_obtener = $SESION->PedirAutorizacion($permiso_nombre, FDW_DATO_PERMISO_OBTENER);
    if($tiene_permiso_obtener) // Si tiene el permiso para obtener los datos del módulo
    {
        $elemento = NULL;
        try // Intentamos inicializar el Elemento
        {
            $elemento = new $elemento_clase($elemento_id);
        }
        catch(Throwable $t) // Error al inicializar el Elemento
        {
            $estado["estado"] = API_ERROR_INICIALIZAR;
            $estado["mensaje"] = utf8_encode("Ocurrió un error al inicializar el Elemento.");
            $estado["debug"] = utf8_encode($t->getMessage());
        }

        if($elemento) // Si el Elemento se inicializa correctamente
        {
            $estado_preparacion_elemento = NULL;
            if($preparar_elemento_inicializado) // Si se especifica un método de preparación del Elemento inicializado
            {
                try // Intentamos preparar el Elemento
                {
                    $estado_preparacion_elemento = $preparar_elemento_inicializado($elemento);
                }
                catch(Throwable $t)
                {
                    $estado_preparacion_elemento = array();
                    $estado_preparacion_elemento["estado"] = API_ERROR_INICIALIZAR;
                    $estado_preparacion_elemento["mensaje"] = utf8_encode("Ocurrió un error al inicializar el Elemento.");
                    $estado_preparacion_elemento["debug"] = utf8_encode($t->getMessage());
                }
            }

            if(!$estado_preparacion_elemento) // Si el Elemento se prepara correctamente
            {
                $estado["estado"] = OK;
                $estado["mensaje"] = utf8_encode("OK");
                $estado["resultado"] = array();

                if($obtener_resultado) // Si se especifica un método para obtener el resutado
                {
                    $estado["resultado"] = $obtener_resultado($elemento);
                }
                else // Si no se especifica el método para obtener el resultado
                {
                    $estado["resultado"]["valores"] = $elemento->ObtenerValores();
                }
            }
            else // Error al preparar el Elemento
            {
                $estado = $estado_preparacion_elemento;
            }
        }
    }
    else // Si no tiene el permiso para obtener los datos del módulo
    {
        $estado["estado"] = SIN_PRIVILEGIOS;
        $estado["mensaje"] = utf8_encode("Sin permiso para obtener el Elemento.");
        $estado["http_response_code"] = 403;
    }

    return $estado;
}

function FDW_POST_Elemento(array $OPENAPI_REQUEST, string $elemento_clase, ?int $elemento_id = NULL, ?callable $preparar_elemento_inicializado = NULL, ?array $campos_a_ignorar = NULL, ?callable $guardar_informacion_adicional = NULL, ?callable $obtener_resultado=NULL, ?callable $procesar_exception_aplicarcambios=NULL):array
{
    global $SESION;

    $estado = array("estado"=>NO_INICIALIZADO, "mensaje"=>"No inicializado", "http_response_code"=>NULL);

    $permiso_nombre = $elemento_clase::ObtenerNombrePermiso();
    $permiso_requerido = $elemento_id ? FDW_DATO_PERMISO_MODIFICAR : FDW_DATO_PERMISO_AGREGAR;

    $tiene_permiso_agregar = $SESION->PedirAutorizacion($permiso_nombre, $permiso_requerido);
    if($tiene_permiso_agregar) // Si tiene el permiso para obtener los datos del módulo
    {
        $elemento = NULL;
        try // Intentamos inicializar el Elemento
        {
            $elemento = new $elemento_clase($elemento_id);
        }
        catch(Throwable $t) // Error al inicializar el Elemento
        {
            $estado["estado"] = API_ERROR_INICIALIZAR;
            $estado["mensaje"] = utf8_encode("Ocurrió un error al inicializar el Elemento.");
            $estado["debug"] = utf8_encode($t->getMessage());
        }

        if($elemento) // Si el Elemento se inicializa correctamente
        {
            // Preparamos el Elemento para ser agregado/modificado
            $estado_preparacion_elemento = NULL;
            if($preparar_elemento_inicializado) // Si se especifica un método de preparación
            {
                try // Intentamos preparar el Elemento
                {
                    $estado_preparacion_elemento = $preparar_elemento_inicializado($elemento);
                }
                catch(Throwable $t)
                {
                    $estado_preparacion_elemento = array();
                    $estado_preparacion_elemento["estado"] = API_ERROR_INICIALIZAR;
                    $estado_preparacion_elemento["mensaje"] = utf8_encode("Ocurrió un error al inicializar el Elemento.");
                    $estado_preparacion_elemento["debug"] = utf8_encode($t->getMessage());
                }
            }

            if(!$estado_preparacion_elemento) // Si no hay error al preparar el Elemento
            {
                $campos_del_sistema = array("id");
                if($campos_a_ignorar) // Si hay campos a ignorar
                {
                    $campos_del_sistema = array_merge($campos_del_sistema, $campos_a_ignorar);
                }

                // Colocamos la información proporcionada
                $elemento_campos = $elemento_clase::ObtenerInformacionDeCampos();

                foreach($OPENAPI_REQUEST["body"] as $campo_nombre=>$_valor_post) // Para cada valor proporcionado
                {
                    if( isset($elemento_campos[$campo_nombre]) ) // Si el campo pertenece al Elemento
                    {
                        // Ignoramos los campos del sistema
                        if( in_array($campo_nombre, $campos_del_sistema) ) {continue;}

                        $campo_informacion = $elemento_campos[$campo_nombre];

                        // Ignoramos los campos de solo lectura
                        if($campo_informacion["soloDeLectura"]){continue;}

                        $valor = Parametro::LimpiarValor($_valor_post, $campo_informacion["tipoDeDato"]);

                        $elemento->AsignarValor($campo_nombre, $valor);
                    }
                }

                // Guardamos la información adicional
                $estado_informacion_adicional = NULL;
                if($guardar_informacion_adicional) // Si se especifica el método para guardar la información adicional
                {
                    try // Intentamos guardar la información adicional
                    {
                        $estado_informacion_adicional = $guardar_informacion_adicional($OPENAPI_REQUEST, $elemento);
                    }
                    catch(Throwable $t)
                    {
                        // Error al guardar la información adicional
                        $estado_informacion_adicional = array();
                        $estado_informacion_adicional["estado"] = INCONSISTENCIA_INTERNA;
                        $estado_informacion_adicional["mensaje"] = utf8_encode("Ocurrió un error al invocar el evento de guardado.");
                        $estado_informacion_adicional["debug"] = utf8_encode($ex->getMessage());
                    }
                }

                if(!$estado_informacion_adicional) // Información adicional guardada correctamente
                {
                    $campo_requeridos_no_proporcionado = $elemento->ObtenerCampoRequeridoNoProporcionado();

                    if(!$campo_requeridos_no_proporcionado) // Si se proporcionan todos los campos requeridos
                    {
                        $campo_no_valido = $elemento->ValidarDatos();

                        if(!$campo_no_valido) // Si el Elemento es válido
                        {
                            try
                            {
                                $elemento->AplicarCambios();

                                $estado["estado"] = OK;
                                $estado["mensaje"] = utf8_encode("OK");
                                $estado["resultado"] = array();

                                if($obtener_resultado) // Si se especifica un método para obtener el resutado
                                {
                                    $estado["resultado"] = $obtener_resultado($elemento);
                                }
                                else // Si no se especifica el método para obtener el resultado
                                {
                                    $estado["resultado"]["valores"] = $elemento->ObtenerValores();
                                }
                            }
                            catch(Exception $ex) // Si ocurre un error al aplicar cambios al Elemento
                            {
                                if($procesar_exception_aplicarcambios) // Si se especifica una función para el procesamiento de la exception
                                {
                                    $estado = $ProcesarExceptionAlAplicarCambios($ex);
                                }
                                else // Si la exception no se procesó por $ProcesarExceptionAlAplicarCambios
                                {
                                    $estado["estado"] = INCONSISTENCIA_INTERNA;
                                    $estado["mensaje"] = utf8_encode("Ocurrió un error al intentar aplicar cambios al Elemento.");
                                    $estado["debug"] = utf8_encode($ex->getMessage());
                                }
                            }
                        }
                        else // Si el Elemento no es válido
                        {
                            $estado["estado"] = API_ELEMENTO_INVALIDO;
                            $estado["mensaje"] = utf8_encode("No se proporcionó un campo requerido por el Elemento.");
                            $estado["campo"] = $campo_no_valido["campo"];
                            $estado["error"] = $campo_no_valido["error"];
                            $estado["http_response_code"] = 400;
                        }
                    }
                    else // Si no se proporcionan todos los campos requeridos
                    {
                        $estado["estado"] = API_FALTA_CAMPO_REQUERIDO;
                        $estado["mensaje"] = utf8_encode("No se proporcionó un campo requerido por el Elemento.");
                        $estado["campo"] = $campo_requeridos_no_proporcionado;
                        $estado["http_response_code"] = 400;
                    }
                }
                else // Error al guardar la información adicional
                {
                    $estado = $estado_informacion_adicional;
                }
            }
            else // Si hay error al preparar el Elemento
            {
                $estado = $estado_preparacion_elemento;
            }
        }
    }
    else // Si no tiene el permiso para obtener los datos del módulo
    {
        $np = $elemento_id ? "modificar" : "agregar";

        $estado["estado"] = SIN_PRIVILEGIOS;
        $estado["mensaje"] = utf8_encode("Sin permisos para {$np} el elemento.");
        $estado["http_response_code"] = 403;
    }

    return $estado;
}

function FDW_DELETE_Elemento(string $elemento_clase, ?int $elemento_id = NULL, ?callable $preparar_elemento_inicializado = NULL):array
{
    global $SESION;

    $estado = array("estado"=>NO_INICIALIZADO, "mensaje"=>"No inicializado", "http_response_code"=>NULL);

    $permiso_nombre = $elemento_clase::ObtenerNombrePermiso();

    $tiene_permiso_eliminar = $SESION->PedirAutorizacion($permiso_nombre, FDW_DATO_PERMISO_ELIMINAR);
    if($tiene_permiso_eliminar) // Si tiene el permiso para obtener los datos del módulo
    {
        $elemento = NULL;
        try // Intentamos inicializar el Elemento
        {
            $elemento = new $elemento_clase($elemento_id);
        }
        catch(Throwable $t) // Error al inicializar el Elemento
        {
            $estado["estado"] = API_ERROR_INICIALIZAR;
            $estado["mensaje"] = utf8_encode("Ocurrió un error al inicializar el Elemento.");
            $estado["debug"] = utf8_encode($t->getMessage());
        }

        if($elemento) // Si el Elemento se inicializa correctamente
        {
            $estado_preparacion_elemento = NULL;
            if($preparar_elemento_inicializado) // Si se especifica un método de preparación del Elemento inicializado
            {
                try // Intentamos preparar el Elemento
                {
                    $estado_preparacion_elemento = $preparar_elemento_inicializado($elemento);
                }
                catch(Throwable $t)
                {
                    $estado_preparacion_elemento = array();
                    $estado_preparacion_elemento["estado"] = API_ERROR_INICIALIZAR;
                    $estado_preparacion_elemento["mensaje"] = utf8_encode("Ocurrió un error al inicializar el Elemento.");
                    $estado_preparacion_elemento["debug"] = utf8_encode($t->getMessage());
                }
            }

            if(!$estado_preparacion_elemento) // Si el Elemento se prepara correctamente
            {
                try
                {
                    $elemento->Eliminar();

                    $estado["estado"] = OK;
                    $estado["mensaje"] = utf8_encode("OK");
                }
                catch(Throwable $t)
                {
                    $estado["estado"] = FDW_ELEMENTO_ELIMINAR_ERROR;
                    $estado["mensaje"] = utf8_encode("Ocurrió un error al eliminar el Elemento.");
                    $estado["debug"] = utf8_encode($t->getMessage());
                }
            }
            else // Error al preparar el Elemento
            {
                $estado = $estado_preparacion_elemento;
            }
        }
    }
    else // Si no tiene el permiso para eliminar El elemento
    {
        $estado["estado"] = SIN_PRIVILEGIOS;
        $estado["mensaje"] = utf8_encode("Sin permisos para eliminar el elemento.");
        $estado["http_response_code"] = 403;
    }

    return $estado;
}
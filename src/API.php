<?php
/**
 * Sistemas Especializados e Innovación Tecnológica, SA de CV
 * Mpsoft.FDW - Framework de Desarrollo Web para PHP
 *
 * v.2.0.0.0 - 2020-09-13
 */

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

                    $estado["estado"] = OK;
                    $estado["mensaje"] = "OK";
                    $estado["_"] = $_;
                    $estado["total"] = $total;
                    $estado["filtrados"] = $filtrados;
                    $estado["resultado"] = $registros;
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

function FDW_POST_Modulo(array $OPENAPI_REQUEST, string $modulo_clase):array
{
    global $SESION;

    $estado = array("estado"=>NO_INICIALIZADO, "mensaje"=>"No inicializado", "http_response_code"=>NULL);

    $permiso_nombre = $modulo_clase::ObtenerNombrePermiso();

    $tiene_permiso_agregar = $SESION->PedirAutorizacion($permiso_nombre, FDW_DATO_PERMISO_AGREGAR);
    if($tiene_permiso_agregar) // Si tiene el permiso para obtener los datos del módulo
    {
        $elemento = new $elemento_clase($elemento_id);
    }
    else // Si no tiene el permiso para obtener los datos del módulo
    {
        $estado["estado"] = SIN_PRIVILEGIOS;
        $estado["mensaje"] = utf8_encode("Sin permisos para agregar el elemento.");
        $estado["http_response_code"] = 403;
    }

    return $estado;
}
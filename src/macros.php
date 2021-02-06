<?php
/**
 * Sistemas Especializados e Innovación Tecnológica, SA de CV
 * Mpsoft.FDW - Framework de Desarrollo Web para PHP
 *
 * v.2.0.0.0 - 2017-03-20
 */

define("OK", 1);                                        // Éxito al llamar la consulta
define("NO_INICIALIZADO", 0);
define("LLAMADA_NO_VALIDA", -1);                        // Error 404. La llamada solicitada no es válida.
define("ERROR_BDD", -2);                                // Error al conectar con el servidor de base de datos.
define("TOKEN_NO_VALIDO", -3);                          // El token proporcionado no es válido
define("SIN_SESION", -4);                               // La sesión no está iniciada
define("INCONSISTENCIA_INTERNA", -5);                   // Algo extraño pasó... Se recibió una respuesta no esperada en el funcionamiento interno de la llamada [Ver parámetro: debug]

define("PARAMETROS_INCORRECTOS", 2);                    // La llamada esperaba parámetros no recibidos [ver parámetro: parametro]
define("SIN_PRIVILEGIOS", 3);                           // El usuario no cuenta con los privilegios necesarios para realizar la llamada

define("FDW_MODULO_INICIO_INVALIDO", 4);                // El inicio solicitado no es válido ( <1 )
define("FDW_MODULO_REGISTROS_INVALIDO", 5);             // La cantidad de registros solicitados no es válido ( <1 ).
define("FDW_MODULO_CAMPO_INEXISTENTE", 6);              // Se solicitó un campo que no existe en el módulo. [ver parámetro: campo]
define("FDW_MODULO_ORDENAMIENTO_INEXISTENTE", 7);       // Se solicitó un campo de ordenamiento que no existe en el módulo. [ver parámetro: campo]
define("FDW_MODULO_BUSQUEDA_INEXISTENTE", 8);           // Se solicitó un campo de búsqueda que no existe en el módulo. [ver parámetro: campo]

define("FDW_ELEMENTO_ELIMINAR_ERROR", 9);               // Error al eliminar el Elemento [Ver parámetro: debug]


// API
define("API_FALTA_CAMPO_REQUERIDO", 100);               // Falta un campo requerido para aplicar cambios en el Elemento
define("API_ELEMENTO_INVALIDO", 101);                   // El Elemento tiene valores que no son válidos
define("API_ERROR_INICIALIZAR", 102);                   // Error al inicializar el Elemento

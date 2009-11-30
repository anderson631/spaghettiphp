<?php
/**
 *  Spaghetti* Framework.
 *
 *  @license   http://www.opensource.org/licenses/mit-license.php The MIT License
 *  @copyright Copyright 2008-2010, Spaghetti* Framework (http://spaghettiphp.org/)
 */

/**
  *  Bem vindo ao Spaghetti*! Esse � o front controller que receber� todas as
  *  requisi��es feitas � sua aplica��o, e estas ser�o enviadas para Dispatcher
  *  para serem processadas e enviarem a resposta ao usu�rio.
  */

/**
  *  O arquivo inclu�do abaixo � o respons�vel pela configura��o e inicializa��o
  *  do Spaghetti*.
  */
require_once dirname(dirname(__FILE__)) . '/config/bootstrap.php';

/**
  *  Importa Dispatcher e dispara uma requisi��o.
  */
import('core.Dispatcher');
Dispatcher::dispatch();
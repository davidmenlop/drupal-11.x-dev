<?php

namespace Drupal\test_module\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response; // Importa la clase Response.
use Drupal\Core\Database\Database;

class TestApiController extends ControllerBase {
  public function getData() {
    // Realiza una consulta para obtener todos los registros de la tabla 'test_form_data'.
    $query = Database::getConnection()->select('test_form_data', 't');
    $query->fields('t', ['id', 'first_name', 'last_name', 'document_type', 'document_number', 'email', 'phone', 'country']);
    $results = $query->execute()->fetchAll(); // Usar fetchAll() para obtener todas las filas.

    // Convertir los resultados a un formato adecuado para JSON.
    $data = [];
    foreach ($results as $result) {
      $data[] = [
        'id' => $result->id,
        'first_name' => $result->first_name,
        'last_name' => $result->last_name,
        'document_type' => $result->document_type,
        'document_number' => $result->document_number,
        'email' => $result->email,
        'phone' => $result->phone,
        'country' => $result->country,
      ];
    }

    // Crear la respuesta JSON.
    $response = new JsonResponse($data);

    // Añadir los encabezados CORS necesarios.
    $response->headers->set('Access-Control-Allow-Origin', '*'); // Cambia '*' por 'http://localhost:3001' en producción.
    $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, OPTIONS');
    $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization');

    // Retornar la respuesta con los encabezados CORS.
    return $response;
  }
}

<?php

namespace Drupal\test_module\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\JsonResponse;
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

    // Retornar los datos en formato JSON.
    return new JsonResponse($data);
  }
}


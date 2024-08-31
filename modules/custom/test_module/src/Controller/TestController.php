<?php

namespace Drupal\test_module\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Database;

class TestController extends ControllerBase {
  public function listData() {
    // Realiza una consulta para obtener todos los registros de la tabla 'test_form_data'.
    $query = Database::getConnection()->select('test_form_data', 't');
    $query->fields('t', ['first_name', 'last_name', 'document_type', 'document_number', 'email', 'phone', 'country']);
    $results = $query->execute()->fetchAll(); // Usar fetchAll() para obtener todas las filas.

    // Verifica que la consulta devuelve resultados.
    if (empty($results)) {
      return [
        '#markup' => $this->t('No data found.'),
      ];
    }

    $rows = [];
    foreach ($results as $result) {
      $rows[] = [
        'data' => [
          $result->first_name,
          $result->last_name,
          $result->document_type,
          $result->document_number,
          $result->email,
          $result->phone,
          $result->country,
        ],
      ];
    }

    // Encabezados de la tabla.
    $header = [
      $this->t('First Name'),
      $this->t('Last Name'),
      $this->t('Document Type'),
      $this->t('Document Number'),
      $this->t('Email'),
      $this->t('Phone'),
      $this->t('Country'),
    ];

    // Devuelve una tabla renderizada con todos los datos.
    return [
      '#type' => 'table',
      '#header' => $header,
      '#rows' => $rows,
    ];
  }
}




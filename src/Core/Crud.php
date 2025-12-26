<?php
namespace Lourdian\MonbelaHotel;

interface Crud {
    public function create(array $data);
    public function update($id, array $data);
    public function getById($id);
    public function delete($id);
    public function getAll();
}

<?php

namespace App\Services;


use Kreait\Firebase\Exception\DatabaseException;

class FirebaseService
{
    private $database;
    private $reference;

    public function get()
    {
        return $this->reference->getSnapshot()->getValue();
    }

    public function set($reference = 'todos'): FirebaseService
    {
        $this->reference = $this->database->getReference($reference);
        return $this;
    }

    public function update($data)
    {
        $this->reference
            ->set($data);
    }

    public function delete()
    {
        $this->reference->remove();
    }

    public function transaction($mapped)
    {
        $this->database->runTransaction(
            function (Database\Transaction $transaction) use($mapped) {
            $transaction->snapshot('options')->set($mapped);
        });
    }
}

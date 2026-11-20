<?php

namespace App\Database;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Validate final write results before PDO commits; discarded savepoints cannot retain guards. */
trait ValidatesBeforeCommit
{
    private array $beforeCommitValidations=[];

    public function hasBeforeCommitValidation(string $key): bool
    {
        return isset($this->beforeCommitValidations[$key]);
    }

    public function beforeCommitValidation(string $key, \Closure $validation): void
    {
        if ($this->transactionLevel() < 1) throw new \LogicException('La validación requiere una transacción activa.');
        if (!$this->hasBeforeCommitValidation($key)) $this->beforeCommitValidations[$key]=[
            'level'=>$this->transactionLevel(),'callback'=>$validation];
    }

    /** Check final state before local output, retaining all guards for the actual commit. */
    public function assertBeforeCommitValidations(): void
    {
        foreach ($this->beforeCommitValidations as $entry) ($entry['callback'])($this);
    }

    private function validateBeforeCommit(): void
    {
        while ($this->beforeCommitValidations) {
            $pending=$this->beforeCommitValidations; $this->beforeCommitValidations=[];
            foreach ($pending as $entry) ($entry['callback'])($this);
        }
    }

    private function retainBeforeCommitValidations(): void
    {
        $level=$this->transactionLevel();
        if ($level===0) {$this->beforeCommitValidations=[];return;}
        foreach ($this->beforeCommitValidations as &$entry) if ($entry['level']>$level) $entry['level']=$level;
        unset($entry);
    }

    public function transaction(\Closure $callback, $attempts=1)
    {
        try {
            return parent::transaction(function ($connection) use ($callback) {
                $result=$callback($connection);
                if ($this->transactionLevel()===1) $this->validateBeforeCommit();
                return $result;
            },$attempts);
        } finally {$this->retainBeforeCommitValidations();}
    }

    public function commit()
    {
        try {
            if ($this->transactionLevel()===1) $this->validateBeforeCommit();
        } catch (\Throwable $error) {
            $this->rollBack(0); throw $error;
        }
        try {parent::commit();}
        finally {$this->retainBeforeCommitValidations();}
    }

    public function rollBack($toLevel=null)
    {
        try {parent::rollBack($toLevel);}
        finally {
            $level=$this->transactionLevel();
            foreach ($this->beforeCommitValidations as $key=>$entry) if ($entry['level']>$level) unset($this->beforeCommitValidations[$key]);
        }
    }

    public function disconnect()
    {
        $this->beforeCommitValidations=[];
        parent::disconnect();
    }
}
// ######### FIN CAMBIO POSTGRESQL 17

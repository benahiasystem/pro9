<?php
namespace App\Services\Payments;

// ######## INICIO VERIFICACION FINAL DE CALCULOS ########
final class CalculationChanged extends \Symfony\Component\HttpKernel\Exception\HttpException
{
    public array $calculation;
    public array $differences;
    public function __construct(array $calculation, array $differences = [])
    {
        $this->calculation = $calculation;
        $this->differences = $differences;
        parent::__construct(409, 'Los cálculos se actualizaron. Revise los valores y confirme nuevamente.');
    }
    public function response()
    {
        return response()->json(['success'=>false, 'code'=>'calculation_changed', 'message'=>$this->getMessage(),
            'calculation'=>$this->calculation, 'differences'=>$this->differences], 409);
    }
}
// ######## FIN VERIFICACION FINAL DE CALCULOS ########

<?php
/**
Copyright 2012-2019 Nick Korbel

This file is part of Booked Scheduler.

Booked Scheduler is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

Booked Scheduler is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with Booked Scheduler.  If not, see <http://www.gnu.org/licenses/>.
 */

class AdminCheckEditOnlyValidation implements IReservationValidationService
{
	/**
	 * @var IReservationValidationService
	 */
	private $serviceToDecorate;

	/**
	 * @var UserSession
	 */
	private $userSession;


	public function __construct(IReservationValidationService $serviceToDecorate,
	 														UserSession $userSession)
	{
		$this->serviceToDecorate = $serviceToDecorate;
		$this->userSession = $userSession;
	}

	public function Validate($series, $retryParameters = null)
	{
		$result = $this->serviceToDecorate->Validate($series, $retryParameters);

		if (!$result->CanBeSaved())
		{
			return $result;
		}



		return $this->EvaluateCustomRule($series);
	}

	private function EvaluateCustomRule($series)
	{
		Log::Debug('Starting AdminCheckEditOnly validation.');
		$configFile = Configuration::Instance()->File('AdminCheckOnly'); // Busca o ficheiro de configuração
		$adminCheckInID = $configFile->GetKey('admincheckonly.attribute.checkin.id'); //Busca o ID configurado de AdminCheckInOnly
		$adminCheckOutID = $configFile->GetKey('admincheckonly.attribute.checkout.id'); //Busca o ID configurado de AdminCheckOutOnly
    $resources = $series->AllResources();
		$adminResources=[]; //Recursos com AdminCheckInOnly
		$checkedIn=true;
		$checkedOut=false;
		$message="";

		//Verfica se é Admin
		if($this->userSession->IsAdmin || $this->userSession->IsResourceAdmin || $this->userSession->IsScheduleAdmin){
		   return new ReservationValidationResult();
		}

    //Verifica se o CheckIn foi efectuado
		//Se não tiver CheckedIn, retorna validação correta
		foreach ($series->Instances() as $instance){
			if(!$instance->IsCheckedIn()){
				return new ReservationValidationResult();
			}
		}

		//Verifica se o CheckOut foi efectuado
		//Se tiver CheckedOut, retorna validação correta
		foreach ($series->Instances() as $instance){
			if($instance->IsCheckedOut()){
				return new ReservationValidationResult();
			}
		}


		//Verifica se algum dos recursos tem AdminChecks
		//Se nenhum tiver AdminCheck, retorna validação válida
		foreach ($resources as $key => $resource) {//Faz um ciclo para todos os recursos e busca AdminCheck

			$attributeRepository = new AttributeRepository();
			$attributes = $attributeRepository->GetEntityValues(4,$resource->GetId());

			foreach($attributes as $attribute){

	                 if($adminCheckInID == $attribute->AttributeId || $adminCheckOutID == $attribute->AttributeId){
	                   $adminCheckOnly = $attribute->Value; //Busca o valor do atributo AdminCheck

										 if($adminCheckOnly){//Se AdminCheckInOnly estiver atribuído no recurso, guarda o nome do recurso
											 array_push($adminResources,$resource->GetName());
										 }
	                 }
								 }
	 					 }

	  // Se houver recursos com AdminCheck
		//Retorna invalidação com mensagem de erro com os nomes dos recursos
	  if($adminResources){
			$adminResources=array_unique($adminResources); //Remove duplicados
			foreach ($adminResources as $adminResourceName){//Organiza os nomes dos recursos de Admin para mostrar ao utilizador
				$message.= $adminResourceName. ", ";
			}
			$message=substr($message, 0, strlen($message)-2); //corta a última vírgula
		  Log::Debug('Validating AdminCheckEditOnly resources, AdminResources?:%s.', $message);

			// Mostra mensagem ao utilizador
			return new ReservationValidationResult(false,
				"A operação não pode ser efectuada porque os seguintes recursos só podem ser validados pelo administrador: ".$message . ".");
		}

		//Se não houver recursos com AdminCheck
		return new ReservationValidationResult();

 }

}

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

class AdminCheckInOnlyValidation implements IReservationValidationService
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
		$configFile = Configuration::Instance()->File('AdminCheckOnly'); // Busca o ficheiro de configuração
		$customAttributeId = $configFile->GetKey('admincheckonly.attribute.checkin.id'); //Busca o ID configurado de AdminCheckInOnly
		$resources = $series->AllResources();
		$adminChecks=0; //Numero de recursos com AdminCheckInOnly
		$userChecks=0;	//Numero de recursos sem AdminCheckInOnly

		foreach ($resources as $key => $resource) {//Faz um ciclo para todos os recursos e busca o atributo costumizado

			$attributeRepository = new AttributeRepository();
			$attributes = $attributeRepository->GetEntityValues(4,$resource->GetId());

			foreach($attributes as $attribute){

	                 if($customAttributeId == $attribute->AttributeId){
	                   $adminCheckOnly = $attribute->Value; //Busca o valor do atributo costumizado AdminCheckInOnly

										 if($adminCheckOnly){
											 $adminChecks++; //Se AdminCheckInOnly estiver atribuído no recurso, aumenta adminChecks

										 }else{
											 $userChecks++; //Senão aumenta $userChecks
										 }
	                 }
								 }
	 					 }
	  $isAdmin = ($this->userSession->IsAdmin || $this->userSession->IsResourceAdmin);
	  Log::Debug('Validating AdminCheckInOnly resources, AdminChecks?:%s. UserChecks?:%s. Is Admin?:%s', $adminChecks, $userChecks, $isAdmin);

		//Muda a mensagem para o utilizador caso haja um recurso que não tenha o atributo AdminCheckInOnly
		if($userChecks){
			$customMessage = $configFile->GetKey('admincheckonly.message.checkin.resource.conflict');
		} else {
			$customMessage = $configFile->GetKey('admincheckonly.message.checkin');
		}

		//Se AdminCheckInOnly estiver atribuído, pelo menos, num recurso
		//e o utilizador não tiver privilégios, impede o Check In
		//enviando a mensagem costumizada
		if($adminChecks && (!$isAdmin)) {
			return new ReservationValidationResult(false, $customMessage);
		}

		// Retorna validação correta caso não haja problemas
		return new ReservationValidationResult();

 }

}

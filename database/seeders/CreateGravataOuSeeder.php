<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Ldap\OrganizationalUnit;

class CreateGravataOuSeeder extends Seeder
{
	public function run(): void
	{
		$baseDn = config('ldap.connections.default.base_dn');

		$ouName = 'gravata';
		$dn = "ou={$ouName},{$baseDn}";

		$ou = OrganizationalUnit::find($dn);
		if (!$ou) {
			$ou = new OrganizationalUnit();
		}

		$ou->setFirstAttribute('ou', $ouName);
		$ou->setFirstAttribute('description', 'Município de Gravatá');
		$ou->setAttribute('objectClass', [
			'top',
			'organizationalUnit',
		]);
		$ou->setDn($dn);
		$ou->save();
	}
}



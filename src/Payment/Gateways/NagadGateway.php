<?php
namespace EilmoCheckout\Payment\Gateways;
defined( 'ABSPATH' ) || exit;
final class NagadGateway extends AbstractMobileGateway { protected $eilmo_brand='Nagad'; public function __construct(){ $this->id=GatewaySettings::NAGAD; parent::__construct(); } }

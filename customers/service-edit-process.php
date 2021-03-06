<?php
/*
	customers/service-edit-process.php

	access: customers_write

	Allows new services to be added to customers, or existing ones to be modified
*/

// includes
require("../include/config.php");
require("../include/amberphplib/main.php");

require("../include/customers/inc_customers.php");
require("../include/services/inc_services.php");
require("../include/services/inc_services_traffic.php");
require("../include/services/inc_services_invoicegen.php");



if (user_permissions_get('customers_write'))
{
	/*
		Load Data
	*/
	$obj_customer				= New customer_services;
	$obj_customer->id			= @security_form_input_predefined("int", "id_customer", 1, "");
	$obj_customer->id_service_customer	= @security_form_input_predefined("int", "id_service_customer", 0, "");


	if ($obj_customer->id_service_customer)
	{
		// load the service data
		$obj_customer->load_data_service();


		// check the date lock status
		if (!$obj_customer->service_check_datechangesafe())
		{
			// start date is adjustable
			$data["date_period_first"]	= @security_form_input_predefined("date", "date_period_first", 1, "");
			$data["date_period_next"]	= $data["date_period_first"];
			
			if ($data["date_period_first"] != $obj_customer->obj_service->data["date_period_first"])
			{
				// date has been adjusted
			}
			else
			{
				// no change
				unset($data["date_period_first"]);
				unset($data["date_period_next"]);
			}
		}

		// standard fields
		$data["active"]			= @security_form_input_predefined("checkbox", "active", 0, "");
		$data["date_period_last"]	= @security_form_input_predefined("date", "date_period_last", 0, "");
		$data["name_service"]		= @security_form_input_predefined("any", "name_service", 0, "");
		$data["description"]		= @security_form_input_predefined("any", "description", 0, "");
		$data["price"]			= @security_form_input_predefined("money", "price", 0, "");
		$data["discount"]		= @security_form_input_predefined("float", "discount", 0, "");

		// options
		$data["quantity"]		= @security_form_input_predefined("int", "quantity", 0, "");

		if (!$data["quantity"])
			$data["quantity"] = 1;	// all services must have at least 1


		// setup fees
		$data["price_setup"]	= @security_form_input_predefined("money", "price_setup", 0, "");
		$data["discount_setup"]	= @security_form_input_predefined("float", "discount_setup", 0, "");

		// billing cdr
		$data["billing_cdr_csv_output"]	= @security_form_input_predefined("checkbox", "billing_cdr_csv_output", 0, "");

		/*
			Fetch Service-Type Options
		*/
		switch ($obj_customer->obj_service->data["typeid_string"])
		{
			case "data_traffic":


				// cap details
				$data["data_traffic_caps"] = array();


				// load all cap information from the selected service.
				$data_traffic_caps = New traffic_caps;

				$data_traffic_caps->id_service		= $obj_customer->obj_service->id;
				$data_traffic_caps->id_service_customer	= $obj_customer->id_service_customer;

				$data_traffic_caps->load_data_traffic_caps();


				for ($i=0; $i < $data_traffic_caps->data_num_rows; $i++)
				{
					$cap = array();

					// fetch traffic cap details
					$cap["type_id"]		= @security_form_input_predefined("int", "traffic_cap_". $i ."_id", 1, "");
					$cap["name"]		= @security_form_input_predefined("any", "traffic_cap_". $i ."_name", 0, "");
					$cap["mode"]		= @security_form_input_predefined("any", "traffic_cap_". $i ."_mode", 0, "");
					$cap["units_included"]	= @security_form_input_predefined("int", "traffic_cap_". $i ."_units_included", 0, "");
					$cap["units_price"]	= @security_form_input_predefined("money", "traffic_cap_". $i ."_units_price", 0, "");

					// any differences to the standard cap?
					if	(
						$cap["mode"]		!= $data_traffic_caps->data[$i]["cap_mode"] ||
						$cap["units_included"]	!= $data_traffic_caps->data[$i]["cap_units_included"] ||
						$cap["units_price"]	!= $data_traffic_caps->data[$i]["cap_units_price"]
						)
					{
						log_write("debug", "process", "Traffic cap type ". $cap["type_id"] ." has been overridden, loading data.");


						// additional checks
						if ($cap["mode"] != "unlimited" && $cap["mode"] != "capped")
						{
							log_write("error", "A data type must either be disabled or marked as capped vs unlimited");
							error_flag("traffic_cap_". $i);
						}

						// add to array
						$data["data_traffic_caps"][] = $cap;
					}
					else
					{
						log_write("debug", "process", "Traffic cap type ". $cap["type_id"] ." has not been altered.");
					}

				}

				unset($data_traffic_caps);

			break;
			

			case "phone_single":
				$data["phone_ddi_single"]		= @security_form_input_predefined("int", "phone_ddi_single", 1, "");
				
				if ($GLOBALS["config"]["SERVICE_CDR_LOCAL"] == "prefix")
				{
					// prefix integer based
					$data["phone_local_prefix"]	= @security_form_input_predefined("int", "phone_local_prefix", 1, "");
				}
				else
				{
					// string/region/destination based
					$data["phone_local_prefix"]	= @security_form_input_predefined("any", "phone_local_prefix", 1, "");
				}
			break;

			case "phone_tollfree":
				$data["phone_ddi_single"]		= @security_form_input_predefined("int", "phone_ddi_single", 1, "");

				$data["phone_trunk_included_units"]	= @security_form_input_predefined("int", "phone_trunk_included_units", 0, "");
				$data["phone_trunk_quantity"]		= @security_form_input_predefined("int", "phone_trunk_quantity", 0, "");
				$data["phone_trunk_price_extra_units"]	= @security_form_input_predefined("money", "phone_trunk_price_extra_units", 0, "");

				if ($data["phone_trunk_quantity"] < $data["phone_trunk_included_units"])
				{
					$data["phone_trunk_quantity"] = $data["phone_trunk_included_units"];
				}

			break;

			case "phone_trunk":
				$data["phone_ddi_included_units"]	= @security_form_input_predefined("int", "phone_ddi_included_units", 0, "");
				$data["phone_ddi_price_extra_units"]	= @security_form_input_predefined("money", "phone_ddi_price_extra_units", 0, "");

				$data["phone_trunk_included_units"]	= @security_form_input_predefined("int", "phone_trunk_included_units", 0, "");
				$data["phone_trunk_quantity"]		= @security_form_input_predefined("int", "phone_trunk_quantity", 0, "");
				$data["phone_trunk_price_extra_units"]	= @security_form_input_predefined("money", "phone_trunk_price_extra_units", 0, "");

				if ($data["phone_trunk_quantity"] < $data["phone_trunk_included_units"])
				{
					$data["phone_trunk_quantity"] = $data["phone_trunk_included_units"];
				}
			break;
		}

	}
	else
	{
		// standard fields
		$data["serviceid"]		= @security_form_input_predefined("any", "serviceid", 1, "");
		$data["date_period_first"]	= @security_form_input_predefined("date", "date_period_first", 1, "");
		$data["date_period_next"]	= $data["date_period_first"];
		$data["description"]		= @security_form_input_predefined("any", "description", 0, "");

		// special migration stuff
		if (sql_get_singlevalue("SELECT value FROM config WHERE name='SERVICE_MIGRATION_MODE'") == 1)
		{
			$data["migration_date_period_usage_override"]		= @security_form_input_predefined("any", "migration_date_period_usage_override", 1, "");

			if ($data["migration_date_period_usage_override"] == "migration_use_usage_date")
			{
				$data["migration_date_period_usage_first"]	= @security_form_input_predefined("date", "migration_date_period_usage_first", 1, "");
			}
		}
	}





	/*
		Verify Data
	*/


	// check that the specified customer actually exists
	if (!$obj_customer->verify_id())
	{
		log_write("error", "process", "The customer you have attempted to edit - ". $obj_customer->id ." - does not exist in this system.");
	}
	else
	{
		if ($obj_customer->id_service_customer)
		{
			// are we editing an existing service? make sure it exists and belongs to this customer
			if (!$obj_customer->verify_id_service_customer())
			{
				log_write("error", "process", "The service you have attempted to edit - ". $obj_customer->id_service_customer ." - does not exist in this system.");
			}
			else
			{
				$obj_customer->load_data();
				$obj_customer->load_data_service();
			}
		}
	}



	// verify the service ID is valid
	if (!$obj_customer->id_service_customer)
	{
		$obj_customer->obj_service->id	= $data["serviceid"];

		if (!$obj_customer->obj_service->verify_id())
		{
			log_write("error", "process", "Unable to find service ". $obj_customer->obj_service->id ."");
		}
		else
		{
			$obj_customer->obj_service->load_data();
		}
	}


	// make sure the last period date is no earlier than the last current service period
	if (!empty($data["date_period_last"]) && $data["date_period_last"] != "0000-00-00")
	{
		// check last current service period
		$sql_obj		= New sql_query;
		$sql_obj->string	= "SELECT date_end FROM `services_customers_periods` WHERE id_service_customer='". $obj_customer->id_service_customer ."' ORDER BY date_end DESC LIMIT 1";
		$sql_obj->execute();

		if ($sql_obj->num_rows())
		{
			$sql_obj->fetch_array();

			if (time_date_to_timestamp($sql_obj->data[0]["date_end"]) > time_date_to_timestamp($data["date_period_last"]))
			{
				// period end date is larger than today - we can not delete the service
				// until after the period completes.

				log_write("error", "process", "Due to active service periods, the last period date can be no later than ". $sql_obj->data[0]["date_end"] ."");
			}

		}

	} // end of last period date check



	/*
		Check for any errors
	*/
	if (error_check())
	{	
		$_SESSION["error"]["form"]["service_view"] = "failed";
		header("Location: ../index.php?page=customers/service-edit.php&id_customer=". $obj_customer->id ."&id_service_customer=". $obj_customer->id_service_customer);
		exit(0);
	}
	else
	{
		if (!$obj_customer->id_service_customer)
		{
			/*
				Add new service
			*/


			// assign service to customer
			$obj_customer->service_add($data["date_period_first"], $migration_options);

			// update service item option information
			$obj_customer->obj_service->option_type			= "customer";
			$obj_customer->obj_service->option_type_id		= $obj_customer->id_service_customer;

			$obj_customer->obj_service->data = array();
			$obj_customer->obj_service->load_data_options();

			$obj_customer->obj_service->data["description"]		= $data["description"];

			$obj_customer->obj_service->action_update_options();



			/*
				Do special migration-specific actions
			*/

			if ($data["migration_date_period_usage_first"])
			{
				/*
					We need to create a special period for usage records, since
					we want the telcomode service to charge for usage but not period
					for the first run.

					We cheat slightly by simply defining the period as being the provided
					date until the service start date.
				*/

				$tmp_date	= explode("-", $data["date_period_first"]);
				$tmp_date 	= date("Y-m-d", mktime(0,0,0,$tmp_date[1], ($tmp_date[2] -1), $tmp_date[0]));

				$sql_obj		= New sql_query;
				$sql_obj->string	= "INSERT INTO services_customers_periods (id_service_customer, date_start, date_end) VALUES ('". $obj_customer->id_service_customer ."', '". $data["migration_date_period_usage_first"] ."', '". $tmp_date  ."')";
				$sql_obj->execute();
			}
		}
		else
		{
			/*
				Adjust an existing service
			"*/


			// enable/disable service if needed
			if ($obj_customer->service_get_status() != $data["active"])
			{
				if ($data["active"])
				{
					// service has been enabled
					$obj_customer->service_enable();

					// generate service period - this won't invoice, but allows us to get a date
					// for when the invoice will be generated
					service_periods_generate($obj_customer->id);
				}
				else
				{
					// service has been disabled
					$obj_customer->service_disable();
				}
			}


			// adjust dates if possible - only possible for services that have yet to be billed, any periods that currently
			// exist are uninvoiced and can be safely deleted.
			if ($data["date_period_first"])
			{
				log_write("notification", "process", "Adjusted service start date to ". time_format_humandate($data["date_period_first"]) ."");

				// handle service information
				$obj_sql		= New sql_query;
				$obj_sql->string	= "UPDATE services_customers SET date_period_first='". $data["date_period_first"] ."',  date_period_next='". $data["date_period_next"] ."' WHERE id='". $obj_customer->id_service_customer ."' LIMIT 1";
				$obj_sql->execute();
				unset($obj_sql);


				// delete service periods
				$obj_sql		= New sql_query;
				$obj_sql->string	= "DELETE FROM services_customers_periods WHERE id_service_customer='". $obj_customer->id_service_customer ."'";
				$obj_sql->execute();
				unset($obj_sql);

				// handle any bundle member services
				$obj_component_sql		= New sql_query;
				$obj_component_sql->string	= "SELECT id FROM services_customers WHERE bundleid='". $obj_customer->id_service_customer ."'";
				$obj_component_sql->execute();

				if ($obj_component_sql->num_rows())
				{
					$obj_component_sql->fetch_array();

					foreach ($obj_component_sql->data as $data_component)
					{
						// adjust service dates
						$obj_sql		= New sql_query;
						$obj_sql->string	= "UPDATE services_customers SET date_period_first='". $data["date_period_first"] ."',  date_period_next='". $data["date_period_next"] ."' WHERE id='". $data_component["id"]  ."' LIMIT 1";
						$obj_sql->execute();

						// delete service periods
						$obj_sql		= New sql_query;
						$obj_sql->string	= "DELETE FROM services_customers_periods WHERE id_service_customer='". $id_component["id"] ."'";
						$obj_sql->execute();
					}
				}

				unset($obj_component_sql);
			}


			// handle end date
			if (!empty($data["date_period_last"]))
			{
				// TODO: why is there no service->action_update function? some work needed to finish
				//       standardising interfaces

				$obj_sql		= New sql_query;
				$obj_sql->string	= "UPDATE services_customers SET date_period_last='". $data["date_period_last"] ."' WHERE id='". $obj_customer->id_service_customer ."' LIMIT 1";
				$obj_sql->execute();
			}


			// clear data so that we can update the options
			$obj_customer->obj_service->data = array();
			$obj_customer->obj_service->load_data_options();

			$obj_customer->obj_service->data["description"]		= $data["description"];
			$obj_customer->obj_service->data["name_service"]	= $data["name_service"];

			$obj_customer->obj_service->data["price"]		= $data["price"];
			$obj_customer->obj_service->data["discount"]		= $data["discount"];
			$obj_customer->obj_service->data["quantity"]		= $data["quantity"];

			$obj_customer->obj_service->data["price_setup"]		= $data["price_setup"];
			$obj_customer->obj_service->data["discount_setup"]	= $data["discount_setup"];

			$obj_customer->obj_service->data["phone_ddi_single"]			= $data["phone_ddi_single"];
			$obj_customer->obj_service->data["phone_local_prefix"]			= $data["phone_local_prefix"];

			$obj_customer->obj_service->data["phone_ddi_included_units"]		= $data["phone_ddi_included_units"];
			$obj_customer->obj_service->data["phone_ddi_price_extra_units"]		= $data["phone_ddi_price_extra_units"];

			$obj_customer->obj_service->data["phone_trunk_included_units"]		= $data["phone_trunk_included_units"];
			$obj_customer->obj_service->data["phone_trunk_quantity"]		= $data["phone_trunk_quantity"];
			$obj_customer->obj_service->data["phone_trunk_price_extra_units"]	= $data["phone_trunk_price_extra_units"];

			$obj_customer->obj_service->data["billing_cdr_csv_output"]    		= $data["billing_cdr_csv_output"];

			$obj_customer->obj_service->action_update_options();


	
			// handle data traffic service cap overrides, these are a bit more complex than regular options
			if ($obj_customer->obj_service->data["typeid_string"] == "data_traffic")
			{
				$sql_obj			= New sql_query;

				// delete existing options
				$sql_obj->string		= "DELETE FROM services_options WHERE option_type='customer' AND option_type_id='". $obj_customer->id_service_customer ."' AND option_name LIKE 'cap_%'";
				$sql_obj->execute();

				// add new cap override options
				foreach ($data["data_traffic_caps"] as $cap)
				{
					log_write("debug", "process", "Creating data traffic cap override for ". $cap["type_id"] ."");

					$sql_obj->string	= "INSERT INTO services_options (option_type, option_type_id, option_name, option_value) VALUES ('customer', '". $obj_customer->id_service_customer ."', 'cap_mode_". $cap["type_id"] ."', '". $cap["mode"] ."')";
					$sql_obj->execute();

					$sql_obj->string	= "INSERT INTO services_options (option_type, option_type_id, option_name, option_value) VALUES ('customer', '". $obj_customer->id_service_customer ."', 'cap_units_included_". $cap["type_id"] ."', '". $cap["units_included"] ."')";
					$sql_obj->execute();
	
					$sql_obj->string	= "INSERT INTO services_options (option_type, option_type_id, option_name, option_value) VALUES ('customer', '". $obj_customer->id_service_customer ."', 'cap_units_price_". $cap["type_id"] ."', '". $cap["units_price"] ."')";
					$sql_obj->execute();
				}
			}



			// do we need to generate a setup fee?
			if ($data["price_setup"] != "0.00" && $data["active"] == 1)
			{
				$obj_customer_order		= New customer_orders;
				$obj_customer_order->id		= $obj_customer->id;
				$obj_customer_order->load_data();

				$obj_customer_order->data_orders["date_ordered"]	= date("Y-m-d");
				$obj_customer_order->data_orders["type"]		= "service";
				$obj_customer_order->data_orders["customid"]		= $obj_customer->obj_service->id;
				$obj_customer_order->data_orders["quantity"]		= "1";
				$obj_customer_order->data_orders["price"]		= $data["price_setup"];
				$obj_customer_order->data_orders["discount"]		= $data["discount_setup"];
				$obj_customer_order->data_orders["description"]		= "Setup Fee: ". $data["name_service"] ."";

				if (!$obj_customer_order->action_update_orders())
				{
					log_write("error", "process", "An unexpected error occured whilst attempting to add an order item to the customer");
				}
				else
				{
					log_write("notification", "process", "Added setup fee of ". format_money($obj_customer_order->data_orders["amount"]) ." to customer orders, this will then be billed automatically.");
				}
			}

		}

		// return to services page
		header("Location: ../index.php?page=customers/service-edit.php&id_customer=". $obj_customer->id ."&id_service_customer=". $obj_customer->id_service_customer ."");
		exit(0);
			
	}

	/////////////////////////
	
}
else
{
	// user does not have perms to view this page/isn't logged on
	error_render_noperms();
	header("Location: ../index.php?page=message.php");
	exit(0);
}


?>

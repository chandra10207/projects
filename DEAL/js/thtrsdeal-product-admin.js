
jQuery(document).ready(function($) {
	// $('#wcv_expiry_date').datepicker();
	// $('#wcv_start_date').datepicker();

	$('#wcv_start_date').datepicker({
        minDate:'today'
    });

    $('#wcv_expiry_date').datepicker({
        minDate:'+1'
    });
		
	// for checkbox toggle
	 $('input.deal_').on('click', function() {
		    $('input.deal_').not(this).prop('checked', false);  
		});

	 function valueChanged()
		{

			if ($('#deal_package').is(":checked") ) {
		     	$(".num_of_days").show();
		     	//$("#general label[for='_regular_price']").text("Price For The Whole Deal ($)");
		     }

		    if($('#deal_per_night').is(":checked") )  { 
		        $(".num_of_days").hide();
		        //$("#general label[for='_regular_price']").text("Price Per Night ($)");
		     }

		     if ($('#deal_per_person').is(":checked") ) {
		     	$(".num_of_days").hide();
		     	//$("#general label[for='_regular_price']").text("Price Per Person ($)");
		     }

		     
		    
		}

	 
});


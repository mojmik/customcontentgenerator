var majaxModule=(function (my) {

const majaxSelect = {
	formatState: function(state) {
		if (!state.id) {
		  return state.text;
		}
		let mValNormalized=my.mStrings.mNormalize(state.element.value);
		let metaKey=state.element.parentElement.name;		
		let mCntVal="("+my.mCounts.getMetaCnt(metaKey,mValNormalized)+")";
		var baseUrl = "/select2-icons";
		var $state = jQuery(
		  `<span>
			  <img class="img-flag" />
			  <span data-cap></span>
			  <span data-cnt="cnt-${mValNormalized}">${mCntVal}</span>
			</span>`
		);
	  
		$state.find("span[data-cap]").text(state.text);
		$state.find("img").attr("src", baseUrl + "/" + mValNormalized + ".png");
	  
		return $state;
      },
      resetAll: () => {
        var selects=jQuery('select[data-group="majax-fields"]');
        selects.each(function (i,obj) {          
           jQuery(obj).val(null);
        });
    }
}

const majaxSlider =  {
	
    setSlidersVals: function(sliderId,val1,val2) {        
        let thisInput=jQuery('input[data-mslider="'+sliderId+'"]');
        let inputName=thisInput.attr('name');
        my.metaMisc.aktMin[inputName]=val1;
        my.metaMisc.aktMax[inputName]=val2;
    },
	initSlidersMinMax: function() {		
        //init sliders with detected min-max values 
		var fs=my.metaMisc.formatMetaVal;
		jQuery('input[data-mslider]').each(function(index) {
			var inputId=this.id;
			let sliderId=jQuery(this).attr('data-mslider');
			let sliderRange=jQuery('#'+sliderId+'');			
			let inputName=jQuery(this).attr('name');
			let valMin=Number(my.metaMisc.valMin[inputName]);
			let valMax=Number(my.metaMisc.valMax[inputName]);
			let fieldFormat=my.metaMisc.fieldformat[inputName];
			fieldFormat = (fieldFormat == "") ? 2 : fieldFormat;
			if (!isNaN(valMin) && !isNaN(valMax)) {	
				let aktMin=my.metaMisc.aktMin[inputName];
				let aktMax=my.metaMisc.aktMax[inputName];
				if (typeof aktMin === 'undefined') aktMin=valMin;
				if (typeof aktMax === 'undefined') aktMax=valMax;				
				jQuery(sliderRange).slider({
					range: true,
					min: valMin,
					max: valMax,
					slide: function( event, ui ) {
						my.metaMisc.aktMin[inputName]=ui.values[0];
						my.metaMisc.aktMax[inputName]=ui.values[1];
						jQuery('#'+inputId).val(fs(ui.values[ 0 ],ui.values[ 1 ],fieldFormat));			
					}
				});	
				
				jQuery(sliderRange).slider("option", "values",[aktMin,aktMax]);
				jQuery(sliderRange).slider("option", "min",valMin);
				jQuery(sliderRange).slider("option", "max",valMax);
							
				jQuery(this).val(fs(aktMin,aktMax,fieldFormat));
			}			
		});
	},
	initSliders: function() {
            //init sliders with default values 0,1
			var fs=my.metaMisc.formatMetaVal;		
			//initialize numeric sliders
			jQuery('input[data-mslider]').each(function(index) {
				var inputId=this.id;
				let sliderId=jQuery(this).attr('data-mslider');
				let sliderRange=jQuery('#'+sliderId+'');
				jQuery(sliderRange).slider({
				range: true,
				min: 0,
				max: 1,
				values: [ 0, 1 ],
				slide: function( event, ui ) {
					my.metaMisc.aktMin[inputName]=ui.values[0];
					my.metaMisc.aktMax[inputName]=ui.values[1];
					jQuery('#'+inputId).val(fs(ui.values[0],ui.values[1],2));			
				}
				});
				sliderRange.on('slidestop',function(e) {			
					my.majaxPrc.runAjax(this);
				});
				jQuery(this).val(fs(jQuery(sliderRange).slider( "values", 0 ),jQuery(sliderRange).slider( "values", 1 ),2));
			})
	}	
}

my.majaxSlider=majaxSlider;
my.majaxSelect=majaxSelect;

return my;
}(majaxModule || {} ));
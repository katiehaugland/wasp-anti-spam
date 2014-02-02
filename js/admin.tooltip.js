jQuery(document).ready(function() {
   jQuery('.form-table a[tooltip]').each(function() {
      jQuery(this).qtip({
         content: jQuery(this).attr('tooltip'), 
         style: 'cream' 
      });
   });
});
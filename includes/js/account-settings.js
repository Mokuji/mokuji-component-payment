jQuery(function($){
  
  var tmplDir = 'mokuji/components/payment/includes/ejs/';
  
  var ensureBoolean = function(data, path){
    
    var target = data;
    
    //Every step before the final one should become objects automatically.
    var i = 0;
    while(path.length-1 > i){
      var next = path[i];
      target[next] = target[next] || {};
      target = target[next];
      i++;
    }
    
    //The last value should be a boolean.
    var last = path[i];
    target[last] = target[last] ? true : false;
    return target[last];
    
  };
  
  var self = window.PaymentAccountSettings = {
    
    el: $('#payment-accounts-container'),
    data: {},
    
    init: function(){
      
      //Debug setting.
      EJS.cache = false;
      
      //Bind events.
      this.el.on('click', '.payment-account', this.onAccountClick);
      this.el.on('click', '.edit-title', this.onTitleEdit);
      this.el.on('blur', '.account-title-editor input', this.onTitleEditorBlur);
      this.el.on('change', '.handler-selector', this.onHandlerChange);
      
      //Do the main lookup for the accounts we've got at the moment.
      this.loadAccounts();
      
    },
    
    loadAccounts: function(){
      return $.rest('GET', '../rest/payment/account')
        .done(function(data){
          
          //Store by ID.
          self.data = {};
          for(var i = 0; i < data.length; i++){
            var account = data[i];
            self.data[account.id] = account;
          }
          
          //Render what we've got.
          self.renderAccounts();
          
        });
    },
    
    renderAccounts: function(){
      
      //Prepare a template and update the DOM.
      var template = new EJS({url: tmplDir+'account-settings.ejs'});
      template.update(self.el[0], self.data);
      
      //It's a form!
      self.el.find('.settings-form').restForm({
        beforeSubmit: function(data, form){
          ensureBoolean(data, ['paypal', 'is_test_mode']);
          ensureBoolean(data, ['ideal', 'is_test_mode']);
        }
      });
      
      //Add in the settings for each selected handler.
      self.el.find('.handler-selector').trigger('change');
      
    },
    
    onAccountClick: function(e){
      self.el.find('.payment-account').removeClass('active');
      $(e.currentTarget).addClass('active');
    },
    
    onTitleEdit: function(e){
      e.preventDefault();
      var $account = $(e.currentTarget).closest('.payment-account');
      $account.find('.account-title').hide();
      $account.find('.account-title-editor').show();
      $account.find('.account-title-editor input').focus().select();
    },
    
    onTitleEditorBlur: function(e){
      var $account = $(e.currentTarget).closest('.payment-account');
      $account.find('.account-title strong').text($account.find('.account-title-editor input').val());
      $account.find('.account-title').show();
      $account.find('.account-title-editor').hide();
    },
    
    onHandlerChange: function(e){
      
      var $method = $(e.currentTarget).closest('.method');
      var handler = $(e.currentTarget).val();
      
      if(handler <= 0){
        $method.find('.settings-container').empty();
        return;
      }
      
      //Which account and method?
      var method = $method.attr('data-method');
      var account = $method.closest('.payment-account').attr('data-account');
      
      //Prepare a template and update the DOM.
      var template = new EJS({url: tmplDir + method+'-'+handler+'-settings.ejs'});
      template.update($method.find('.settings-container')[0], self.data[account][method] || {settings_object: {}});
      
    }
    
  };
  
  //Run init :D
  self.init();
  
});
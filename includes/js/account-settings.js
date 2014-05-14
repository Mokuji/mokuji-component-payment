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
  
  var esc = function(input){
    return input.replace(/"/g, '&quot;');
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
      this.el.on('click', '.add-account', this.onAddAccount);
      this.el.on('blur', '.account-title-editor input', this.onTitleEditorBlur);
      this.el.on('change', '.handler-selector', this.onHandlerChange);
      this.el.on('click', '.delete-account', this.onDeleteAccount);
      
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
      template.update(self.el[0], {accounts: self.data, 'esc':esc});
      
      //It's a form!
      self.el.find('.settings-form').restForm({
        beforeSubmit: function(data, form){
          ensureBoolean(data, ['paypal', 'is_test_mode']);
          ensureBoolean(data, ['ideal', 'is_test_mode']);
        },
        success: function(data){
          self.data[data.id] = data;
        }
      });
      
      //Add in the settings for each selected handler.
      self.el.find('.handler-selector').trigger('change');
      
    },
    
    onAddAccount: function(e){
      
      e.preventDefault();
      
      //Prepare a template and update the DOM.
      var template = new EJS({url: tmplDir+'account-settings.ejs'});
      var html = template.render({'esc':esc, accounts:{0:{}}});
      self.el.find('.add-account').remove();
      self.el.find('.payment-account').removeClass('active');
      self.el.append(html);
      
      //It's a form!
      self.el.find('.settings-form').restForm({
        beforeSubmit: function(data, form){
          ensureBoolean(data, ['paypal', 'is_test_mode']);
          ensureBoolean(data, ['ideal', 'is_test_mode']);
        }
      });
      
    },
    
    onDeleteAccount: function(e){
      
      e.preventDefault();
      
      var $form = $(e.target).closest('.settings-form');
      
      var removeIt = function(){
        $form.closest('.payment-account').remove();
      };
      
      if($form.attr('method') == 'PUT')
        $.rest('DELETE', $form.attr('action')).done(removeIt);
      else
        removeIt();
      
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
      var $input = $account.find('.account-title-editor input');
      
      //Only change title when input is not empty.
      if($input.val() !== ''){
        $account.find('.account-title strong').text($account.find('.account-title-editor input').val());
      }
      
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
      
      //Prepare the data. Defaulting to an empty object with a few required sub-elements.
      var data = (self.data[account] && self.data[account][method]) || {settings_object: {}};
      if(!data.settings_object) data.settings_object = {};
      $.extend(data, {'esc':esc});
      
      //Prepare a template and update the DOM.
      var template = new EJS({url: tmplDir + method+'-'+handler+'-settings.ejs'});
      template.update($method.find('.settings-container')[0], data);
      
    }
    
  };
  
  //EJS is loaded asynchronously, so see if it's there yet.
  var initAttempts = 5;
  var tryInit = function(){
    
    if(!EJS && initAttempts > 0){
      
      initAttempts--;
      return setTimeout(tryInit, 200);
      
    }
    
    //We're ready!
    self.init();
    
  };
  
  //Have a go at the init.
  tryInit();
  
});
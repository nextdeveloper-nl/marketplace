- Merged translation table with product table. Because it was unnecessary.
- Adding language_id to understand in which language the text has been written
- Added management_class to add certain features like subscribe, pricing etc.


# Management Class responsibilities
- create subscription: this method will be responsible from creating and provisioning the service on behalf of the customer. If this is a licence, then this function is responsible for applying the licence. If this is a product then it is responsible from creating the product.
- show subscription: this method will show custom information about product subscription. This is used when needed.
- update subscription: This method generally being used to change subscription data or change subscription expiration date
- delete subscription: This method will delete the subscription and all its information as well as 3rd party data if exists
- validate subscription: this method checks if the subscription options are still valid and this subscription is usable. For example: when a customer wants to buy a product or server and server is licenced for only 2 CPUs but customer wants to use 4 vCPU's. This means that this subscription is not valid anymore. The customer should buy another subsription for this product.
- calculatePrice: this method will calculate the price of the subscription, maybe there is a special discount for a customer type or special price.

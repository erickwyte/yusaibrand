<?php
// migrate-carts.php
// Run this once to migrate black market cart to main cart
?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Migrate black market cart to main cart
    const blackMarketCart = JSON.parse(localStorage.getItem('blackMarketCart')) || [];
    const cart = JSON.parse(localStorage.getItem('cart')) || [];
    
    if (blackMarketCart.length > 0) {
        // Merge carts
        blackMarketCart.forEach(blackItem => {
            const existingItem = cart.find(item => item.id === blackItem.id);
            
            if (existingItem) {
                existingItem.quantity += blackItem.quantity;
            } else {
                cart.push({
                    id: blackItem.id,
                    name: blackItem.name,
                    price: blackItem.price,
                    quantity: blackItem.quantity,
                    image: blackItem.image || 'default-product.jpg',
                    addedAt: blackItem.addedAt || new Date().toISOString()
                });
            }
        });
        
        // Save merged cart
        localStorage.setItem('cart', JSON.stringify(cart));
        
        // Clear black market cart
        localStorage.removeItem('blackMarketCart');
        
        console.log('Cart migration completed:', blackMarketCart.length, 'items migrated');
        
        // Update cart count
        const totalItems = cart.reduce((sum, item) => sum + item.quantity, 0);
        document.querySelectorAll('.cart-count').forEach(el => {
            el.textContent = totalItems;
        });
    }
});
</script>
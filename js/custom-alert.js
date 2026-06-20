// Chỉ 1 file duy nhất - thay thế mọi alert() và thông báo

(function() {
    // Tạo khung thông báo toast (góc phải, tự tắt)
    if (!document.getElementById('toastContainer')) {
        document.body.insertAdjacentHTML('beforeend', `
            <div id="toastContainer" style="position:fixed;top:70px;right:20px;z-index:99999"></div>
            <style>
                .toast{background:#28a745;color:white;padding:12px 20px;border-radius:8px;margin-bottom:10px;animation:slideIn 0.3s;font-size:14px}
                .toast.error{background:#dc3545}
                .toast.warning{background:#ff9800}
                @keyframes slideIn{from{transform:translateX(100%);opacity:0}to{transform:translateX(0);opacity:1}}
            </style>
        `);
    }

    // Hàm thông báo nhanh - DÙNG CHO MỌI TRƯỜNG HỢP
    window.toast = function(message, type = 'success') {
        const container = document.getElementById('toastContainer');
        const toast = document.createElement('div');
        toast.className = `toast ${type}`;
        toast.innerHTML = message;
        container.appendChild(toast);
        setTimeout(() => { toast.remove() }, 2500);
    };

    // Ghi đè alert() - xóa sạch "localhost cho biết"
    window.alert = function(msg) {
        const modal = document.createElement('div');
        modal.style.cssText = 'position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);display:flex;justify-content:center;align-items:center;z-index:100000';
        modal.innerHTML = `<div style="background:white;padding:25px 30px;border-radius:16px;text-align:center;min-width:280px"><p style="margin:0 0 20px">${msg}</p><button id="okBtn" style="background:#007bff;color:white;border:none;padding:8px 30px;border-radius:8px;cursor:pointer">OK</button></div>`;
        document.body.appendChild(modal);
        document.getElementById('okBtn').onclick = () => modal.remove();
    };
})();
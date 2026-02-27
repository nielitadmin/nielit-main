// Modern Toast Notification System
class ToastNotification {
    constructor() {
        this.container = null;
        this.init();
    }

    init() {
        // Create toast container if it doesn't exist
        if (!document.getElementById('toast-container')) {
            this.container = document.createElement('div');
            this.container.id = 'toast-container';
            this.container.className = 'toast-container';
            document.body.appendChild(this.container);
        } else {
            this.container = document.getElementById('toast-container');
        }
    }

    show(message, type = 'info', duration = 4000) {
        const toast = document.createElement('div');
        toast.className = `toast toast-${type} toast-enter`;
        
        const icons = {
            success: '<i class="fas fa-check-circle"></i>',
            error: '<i class="fas fa-exclamation-circle"></i>',
            warning: '<i class="fas fa-exclamation-triangle"></i>',
            info: '<i class="fas fa-info-circle"></i>',
            loading: '<i class="fas fa-spinner fa-spin"></i>'
        };

        toast.innerHTML = `
            <div class="toast-icon">${icons[type] || icons.info}</div>
            <div class="toast-content">
                <div class="toast-message">${message}</div>
            </div>
            <button class="toast-close" onclick="this.parentElement.remove()">
                <i class="fas fa-times"></i>
            </button>
        `;

        this.container.appendChild(toast);

        // Trigger animation
        setTimeout(() => {
            toast.classList.remove('toast-enter');
            toast.classList.add('toast-show');
        }, 10);

        // Auto remove after duration (unless it's a loading toast)
        if (type !== 'loading' && duration > 0) {
            setTimeout(() => {
                this.remove(toast);
            }, duration);
        }

        return toast;
    }

    remove(toast) {
        toast.classList.remove('toast-show');
        toast.classList.add('toast-exit');
        setTimeout(() => {
            if (toast.parentElement) {
                toast.parentElement.removeChild(toast);
            }
        }, 300);
    }

    success(message, duration = 4000) {
        return this.show(message, 'success', duration);
    }

    error(message, duration = 5000) {
        return this.show(message, 'error', duration);
    }

    warning(message, duration = 4000) {
        return this.show(message, 'warning', duration);
    }

    info(message, duration = 4000) {
        return this.show(message, 'info', duration);
    }

    loading(message) {
        return this.show(message, 'loading', 0);
    }
}

// Initialize global toast instance
const toast = new ToastNotification();

// Global showToast function for backward compatibility
function showToast(message, type = 'info', duration = 4000) {
    return toast.show(message, type, duration);
}

// Modern Confirm Dialog
function showConfirm(options) {
    return new Promise((resolve) => {
        const modal = document.createElement('div');
        modal.className = 'confirm-modal';
        modal.innerHTML = `
            <div class="confirm-overlay"></div>
            <div class="confirm-dialog">
                <div class="confirm-icon ${options.type || 'warning'}">
                    <i class="fas fa-${options.type === 'danger' ? 'exclamation-triangle' : 'question-circle'}"></i>
                </div>
                <h3 class="confirm-title">${options.title || 'Confirm Action'}</h3>
                <p class="confirm-message">${options.message || 'Are you sure?'}</p>
                <div class="confirm-buttons">
                    <button class="btn btn-secondary confirm-cancel">
                        <i class="fas fa-times"></i> ${options.cancelText || 'Cancel'}
                    </button>
                    <button class="btn btn-${options.type === 'danger' ? 'danger' : 'primary'} confirm-ok">
                        <i class="fas fa-check"></i> ${options.confirmText || 'Confirm'}
                    </button>
                </div>
            </div>
        `;

        document.body.appendChild(modal);

        // Trigger animation
        setTimeout(() => modal.classList.add('show'), 10);

        const cleanup = (result) => {
            modal.classList.remove('show');
            setTimeout(() => {
                document.body.removeChild(modal);
                resolve(result);
            }, 300);
        };

        modal.querySelector('.confirm-cancel').onclick = () => cleanup(false);
        modal.querySelector('.confirm-ok').onclick = () => cleanup(true);
        modal.querySelector('.confirm-overlay').onclick = () => cleanup(false);
    });
}

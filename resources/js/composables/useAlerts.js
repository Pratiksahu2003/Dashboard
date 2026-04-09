import Swal from 'sweetalert2';

export const useAlerts = () => {
    const success = (text, title = 'Success') => Swal.fire({
        icon: 'success',
        title,
        text,
        confirmButtonColor: '#0f172a',
    });

    const error = (text, title = 'Action Failed') => Swal.fire({
        icon: 'error',
        title,
        text,
        confirmButtonColor: '#b91c1c',
    });

    const info = (text, title = 'Info') => Swal.fire({
        icon: 'info',
        title,
        text,
        confirmButtonColor: '#0f172a',
    });

    const confirmDanger = async ({
        title = 'Are you sure?',
        text = 'This action cannot be undone.',
        confirmText = 'Yes, continue',
        cancelText = 'Cancel',
    } = {}) => {
        const result = await Swal.fire({
            icon: 'warning',
            title,
            text,
            showCancelButton: true,
            confirmButtonText: confirmText,
            cancelButtonText: cancelText,
            confirmButtonColor: '#b91c1c',
        });
        return result.isConfirmed;
    };

    return {
        success,
        error,
        info,
        confirmDanger,
    };
};

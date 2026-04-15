import type { SVGAttributes } from 'react';

const yellow = '#F9D14B';
const white = '#FFFFFF';

export default function AppLogoIcon(props: SVGAttributes<SVGElement>) {
    return (
        <svg {...props} viewBox="0 0 40 40" xmlns="http://www.w3.org/2000/svg">
            <circle cx="20" cy="20" r="18" fill={yellow} />
            {/* Upper Z bar + diagonal (gap shows yellow between this and lower piece) */}
            <path fill={white} d="M9.5 12L27.5 9.5L31 12.8L29.5 14.8L11.2 17.8L9.5 12Z" />
            {/* Lower lightning leg */}
            <path
                fill={white}
                d="M12.5 19.5L30 16.2L33 21.2L30 25.8L26.5 27.8L20 35L15.5 31L10.8 23.5L12.5 19.5Z"
            />
        </svg>
    );
}

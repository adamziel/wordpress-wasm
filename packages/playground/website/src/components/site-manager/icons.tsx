import { SiteLogo } from '../../lib/site-storage';

export const Logo = (props?: React.SVGProps<SVGSVGElement>) => {
	return (
		<svg
			width="32"
			height="32"
			viewBox="0 0 32 32"
			fill="none"
			xmlns="http://www.w3.org/2000/svg"
			{...props}
		>
			<rect
				width="10.4176"
				height="10.4176"
				rx="3.86258"
				transform="matrix(0.829038 -0.559193 0.838671 0.544639 7.45703 24.1775)"
				stroke="white"
				strokeWidth="0.965644"
			/>
			<rect
				width="13.2346"
				height="13.2346"
				rx="3.86258"
				transform="matrix(0.829038 -0.559193 0.838671 0.544639 5.0918 18.9934)"
				stroke="white"
				strokeWidth="1.44847"
			/>
			<rect
				width="17.451"
				height="17.451"
				rx="3.86258"
				transform="matrix(0.829038 -0.559193 0.838671 0.544639 1.55371 11.6099)"
				stroke="white"
				strokeWidth="1.93129"
			/>
		</svg>
	);
};

export const TemporaryStorageIcon = (props?: React.SVGProps<SVGSVGElement>) => {
	return (
		<svg
			width="16"
			height="17"
			viewBox="0 0 16 17"
			fill="none"
			xmlns="http://www.w3.org/2000/svg"
			{...props}
		>
			<path
				fillRule="evenodd"
				clipRule="evenodd"
				d="M8 15C4.41015 15 1.5 12.0899 1.5 8.5C1.5 4.91015 4.41015 2 8 2C11.5899 2 14.5 4.91015 14.5 8.5C14.5 12.0899 11.5899 15 8 15ZM0 8.5C0 4.08172 3.58172 0.5 8 0.5C12.4183 0.5 16 4.08172 16 8.5C16 12.9183 12.4183 16.5 8 16.5C3.58172 16.5 0 12.9183 0 8.5ZM9 9.5V4.5H7.5V8H5.5V9.5H9Z"
				fill="#949494"
			/>
		</svg>
	);
};

export const WordPressIcon = (props?: React.SVGProps<SVGSVGElement>) => {
	return (
		<svg
			width="20"
			height="21"
			viewBox="0 0 20 21"
			fill="none"
			xmlns="http://www.w3.org/2000/svg"
			{...props}
		>
			<path
				d="M20 10.5C20 4.99 15.51 0.5 10 0.5C4.48 0.5 0 4.99 0 10.5C0 16.02 4.48 20.5 10 20.5C15.51 20.5 20 16.02 20 10.5ZM7.78 15.87L4.37 6.72C4.92 6.7 5.54 6.64 5.54 6.64C6.04 6.58 5.98 5.51 5.48 5.53C5.48 5.53 4.03 5.64 3.11 5.64C2.93 5.64 2.74 5.64 2.53 5.63C4.12 3.19 6.87 1.61 10 1.61C12.33 1.61 14.45 2.48 16.05 3.95C15.37 3.84 14.4 4.34 14.4 5.53C14.4 6.27 14.85 6.89 15.3 7.63C15.65 8.24 15.85 8.99 15.85 10.09C15.85 11.58 14.45 15.09 14.45 15.09L11.42 6.72C11.96 6.7 12.24 6.55 12.24 6.55C12.74 6.5 12.68 5.3 12.18 5.33C12.18 5.33 10.74 5.45 9.8 5.45C8.93 5.45 7.47 5.33 7.47 5.33C6.97 5.3 6.91 6.53 7.41 6.55L8.33 6.63L9.59 10.04L7.78 15.87ZM17.41 10.5C17.65 9.86 18.15 8.63 17.84 6.25C18.54 7.54 18.89 8.96 18.89 10.5C18.89 13.79 17.16 16.74 14.49 18.28C15.46 15.69 16.43 13.08 17.41 10.5ZM6.1 18.59C3.12 17.15 1.11 14.03 1.11 10.5C1.11 9.2 1.34 8.02 1.83 6.91C3.25 10.8 4.67 14.7 6.1 18.59ZM10.13 11.96L12.71 18.94C11.85 19.23 10.95 19.39 10 19.39C9.21 19.39 8.43 19.28 7.71 19.06C8.52 16.68 9.33 14.32 10.13 11.96Z"
				fill="#ffffff"
			/>
		</svg>
	);
};

export function getLogoDataURL(logo: SiteLogo): string {
	return `data:${logo.mime};base64,${logo.data}`;
}

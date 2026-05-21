import { describe, it, expect } from 'vitest';
import { render, screen } from '@testing-library/react';
import { InstagramEmbed } from '@/components/directorio/InstagramEmbed';

describe('InstagramEmbed', () => {
  it('renders a link to the Instagram profile', () => {
    render(<InstagramEmbed handle="cafe_aria" />);
    const link = screen.getByRole('link');
    expect(link.getAttribute('href')).toBe('https://www.instagram.com/cafe_aria/');
    expect(link.getAttribute('target')).toBe('_blank');
    expect(link.getAttribute('rel')).toMatch(/noreferrer/);
  });

  it('strips a leading @ from the handle', () => {
    render(<InstagramEmbed handle="@plaza_aria" />);
    const link = screen.getByRole('link');
    expect(link.getAttribute('href')).toBe('https://www.instagram.com/plaza_aria/');
    expect(screen.getByText('@plaza_aria')).toBeInTheDocument();
  });

  it('trims whitespace', () => {
    render(<InstagramEmbed handle="  cafe_aria  " />);
    expect(screen.getByRole('link').getAttribute('href')).toBe('https://www.instagram.com/cafe_aria/');
  });

  it('returns null for missing handle', () => {
    const { container } = render(<InstagramEmbed handle={null} />);
    expect(container.firstChild).toBeNull();
  });

  it('returns null for empty string', () => {
    const { container } = render(<InstagramEmbed handle="" />);
    expect(container.firstChild).toBeNull();
  });

  it('returns null for whitespace-only', () => {
    const { container } = render(<InstagramEmbed handle="   " />);
    expect(container.firstChild).toBeNull();
  });

  it('exposes an accessible label', () => {
    render(<InstagramEmbed handle="cafe_aria" />);
    expect(screen.getByRole('link').getAttribute('aria-label')).toMatch(/@cafe_aria/);
  });
});
